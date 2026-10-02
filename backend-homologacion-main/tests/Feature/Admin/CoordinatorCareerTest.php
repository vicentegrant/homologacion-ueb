<?php

namespace Tests\Feature\Admin;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\EstudianteCarrera;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoordinatorCareerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_put_replaces_coordinator_careers_without_duplicates(): void
    {
        $this->authenticateAdministrator();
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');
        $first = Carrera::query()->create(['nombre' => 'Software']);
        $second = Carrera::query()->create(['nombre' => 'Telecomunicaciones']);
        $third = Carrera::query()->create(['nombre' => 'Industrial']);
        $coordinator->carrerasCoordinadas()->sync([$first->id]);

        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", [
            'carrera_ids' => [$second->id, $third->id],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseMissing('coordinador_carreras', ['coordinador_id' => $coordinator->id, 'carrera_id' => $first->id]);
        $this->assertDatabaseHas('coordinador_carreras', ['coordinador_id' => $coordinator->id, 'carrera_id' => $second->id]);
        $this->assertSame(2, $coordinator->carrerasCoordinadas()->count());

        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", [
            'carrera_ids' => [$second->id, $second->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('carrera_ids.1');
    }

    public function test_careers_cannot_be_assigned_to_a_non_coordinator(): void
    {
        $this->authenticateAdministrator();
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $career = Carrera::query()->create(['nombre' => 'Software']);

        $this->putJson("/api/v1/admin/coordinators/{$student->id}/careers", [
            'carrera_ids' => [$career->id],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('coordinador_carreras', 0);
    }

    public function test_career_catalog_is_available_to_administrator(): void
    {
        $this->authenticateAdministrator();
        Carrera::query()->create(['nombre' => 'Software']);

        $this->getJson('/api/v1/admin/careers')->assertOk()->assertJsonPath('data.0.nombre', 'Software');
    }

    public function test_removing_a_career_with_students_returns_409_without_changing_any_assignment(): void
    {
        $this->authenticateAdministrator();
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');
        $student = User::factory()->create();
        $occupied = Carrera::query()->create(['nombre' => 'Software']);
        $empty = Carrera::query()->create(['nombre' => 'Industrial']);
        $replacement = Carrera::query()->create(['nombre' => 'Telecomunicaciones']);
        $coordinator->carrerasCoordinadas()->sync([$occupied->id, $empty->id]);
        $assignment = CoordinadorCarrera::query()->where('coordinador_id', $coordinator->id)
            ->where('carrera_id', $occupied->id)->firstOrFail();
        $enrollment = EstudianteCarrera::query()->create([
            'estudiante_id' => $student->id,
            'coordinador_carrera_id' => $assignment->id,
        ]);

        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", [
            'carrera_ids' => [$replacement->id],
        ])->assertStatus(409)->assertExactJson([
            'success' => false,
            'message' => 'No puede retirar carreras con estudiantes asignados. Reasigne los estudiantes primero.',
        ]);

        $this->assertModelExists($enrollment);
        $this->assertModelExists($assignment);
        $this->assertDatabaseHas('coordinador_carreras', ['coordinador_id' => $coordinator->id, 'carrera_id' => $empty->id]);
        $this->assertDatabaseMissing('coordinador_carreras', ['coordinador_id' => $coordinator->id, 'carrera_id' => $replacement->id]);
    }

    public function test_retaining_an_occupied_career_preserves_student_assignments(): void
    {
        $this->authenticateAdministrator();
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');
        $career = Carrera::query()->create(['nombre' => 'Software']);
        $additional = Carrera::query()->create(['nombre' => 'Industrial']);
        $assignment = CoordinadorCarrera::query()->create(['coordinador_id' => $coordinator->id, 'carrera_id' => $career->id]);
        $enrollment = EstudianteCarrera::query()->create([
            'estudiante_id' => User::factory()->create()->id,
            'coordinador_carrera_id' => $assignment->id,
        ]);

        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", [
            'carrera_ids' => [(string) $career->id, (string) $additional->id],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->assertModelExists($enrollment);
        $this->assertModelExists($assignment);
        $this->assertDatabaseHas('coordinador_carreras', ['coordinador_id' => $coordinator->id, 'carrera_id' => $additional->id]);
    }

    public function test_database_rejects_deleting_a_coordination_with_students(): void
    {
        $coordinator = User::factory()->create();
        $career = Carrera::query()->create(['nombre' => 'Software']);
        $assignment = CoordinadorCarrera::query()->create(['coordinador_id' => $coordinator->id, 'carrera_id' => $career->id]);
        $enrollment = EstudianteCarrera::query()->create([
            'estudiante_id' => User::factory()->create()->id,
            'coordinador_carrera_id' => $assignment->id,
        ]);

        try {
            DB::transaction(fn () => $assignment->delete());
            $this->fail('La base de datos debe impedir eliminar una coordinación con estudiantes.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->errorInfo[0]);
        }

        $this->assertModelExists($assignment);
        $this->assertModelExists($enrollment);
    }

    private function authenticateAdministrator(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        $this->withToken($admin->createToken('test')->plainTextToken);

        return $admin;
    }
}
