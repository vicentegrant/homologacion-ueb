<?php

namespace Tests\Feature\Admin;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\EstudianteCarrera;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CoordinatorCareerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_configures_academic_offer_and_assigns_a_new_coordinator(): void
    {
        $this->authenticateAdministrator();
        Notification::fake();
        $faculty = $this->postJson('/api/v1/admin/catalogs/facultades', [
            'nombre' => 'Facultad de Ingeniería', 'activa' => true,
        ])->assertCreated()->json('data.id');
        $modality = $this->postJson('/api/v1/admin/catalogs/modalidades', [
            'nombre' => 'Presencial de prueba', 'activa' => true,
        ])->assertCreated()->json('data.id');
        $career = $this->postJson('/api/v1/admin/catalogs/carreras', [
            'nombre' => 'Ingeniería de Software', 'activa' => true,
            'facultad_id' => $faculty, 'modalidad_ids' => [$modality],
        ])->assertCreated()->assertJsonPath('data.facultad', 'Facultad de Ingeniería')
            ->assertJsonPath('data.modalidades.0.id', $modality)->json('data.id');
        $coordinatorId = $this->postJson('/api/v1/admin/users', [
            'nombres_completos' => 'Coordinador de Ingeniería',
            'tipo_identificacion' => 'cedula', 'cedula' => '0200000008',
            'email' => 'coordinador.ingenieria@example.test', 'numero_celular' => '0991234567',
            'rol_id' => Role::query()->where('nombre', 'coordinador')->firstOrFail()->id,
            'carrera_ids' => [$career],
        ])->assertCreated()->assertJsonPath('data.roles.0', 'coordinador')
            ->assertJsonPath('data.carreras.0.id', $career)->json('data.id');

        $this->putJson("/api/v1/admin/coordinators/{$coordinatorId}/careers", [
            'carrera_ids' => [$career],
        ])->assertOk()->assertJsonPath('data.0.id', $career);
        $this->getJson("/api/v1/admin/coordinators/{$coordinatorId}/careers")
            ->assertOk()->assertJsonPath('data.0.id', $career);
        $this->getJson('/api/v1/admin/users?search=coordinador.ingenieria')
            ->assertOk()->assertJsonPath('data.0.carreras.0.id', $career);

        $coordinator = User::query()->findOrFail($coordinatorId);
        $this->assertTrue($coordinator->must_change_password);
        $this->assertTrue(Hash::check('0200000008', $coordinator->password));
        Notification::assertSentTo($coordinator, TemporaryPasswordNotification::class);
        $this->assertDatabaseHas('carreras', ['id' => $career, 'facultad_id' => $faculty]);
        $this->assertDatabaseHas('coordinador_carreras', ['coordinador_id' => $coordinatorId, 'carrera_id' => $career]);
    }

    public function test_empty_selection_clears_unused_careers_but_cannot_clear_occupied_careers(): void
    {
        $this->authenticateAdministrator();
        $coordinator = User::factory()->create();
        $coordinator->assignRole('coordinador');
        $career = Carrera::query()->create(['nombre' => 'Software']);
        $coordinator->carrerasCoordinadas()->sync([$career->id]);

        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", ['carrera_ids' => []])
            ->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseMissing('coordinador_carreras', ['coordinador_id' => $coordinator->id]);
        $assignment = CoordinadorCarrera::query()->create(['coordinador_id' => $coordinator->id, 'carrera_id' => $career->id]);
        $enrollment = EstudianteCarrera::query()->create([
            'estudiante_id' => User::factory()->create()->id, 'coordinador_carrera_id' => $assignment->id,
        ]);
        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", ['carrera_ids' => []])->assertStatus(409);
        $this->assertModelExists($assignment);
        $this->assertModelExists($enrollment);
        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", [])->assertUnprocessable();
    }

    public function test_registration_rolls_back_if_careers_are_invalid_or_role_is_not_coordinator(): void
    {
        $this->authenticateAdministrator();
        Notification::fake();
        $inactive = Carrera::query()->create(['nombre' => 'Carrera suspendida', 'activa' => false]);
        $active = Carrera::query()->create(['nombre' => 'Carrera disponible', 'activa' => true]);
        $payload = [
            'nombres_completos' => 'Coordinador sin crear', 'tipo_identificacion' => 'cedula',
            'cedula' => '0200000008', 'email' => 'rollback@example.test', 'numero_celular' => '0991234567',
            'rol_id' => Role::query()->where('nombre', 'coordinador')->firstOrFail()->id,
        ];
        foreach ([[], [$inactive->id], [$active->id, $active->id], [999999]] as $ids) {
            $this->postJson('/api/v1/admin/users', [...$payload, 'carrera_ids' => $ids])->assertUnprocessable();
            $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        }
        $this->postJson('/api/v1/admin/users', [...$payload,
            'rol_id' => Role::query()->where('nombre', 'estudiante')->firstOrFail()->id,
            'carrera_ids' => [$active->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('carrera_ids');
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        Notification::assertNothingSent();
    }

    public function test_editing_coordinator_saves_careers_and_rolls_back_profile_when_removal_is_blocked(): void
    {
        $this->authenticateAdministrator();
        $coordinator = User::factory()->create(['nombres_completos' => 'Nombre original']);
        $coordinator->assignRole('coordinador');
        $career = Carrera::query()->create(['nombre' => 'Carrera asignable', 'activa' => true]);
        $this->patchJson("/api/v1/admin/users/{$coordinator->id}", ['carrera_ids' => [$career->id]])
            ->assertOk()->assertJsonPath('data.carreras.0.id', $career->id);
        $assignment = $coordinator->coordinaciones()->firstOrFail();
        $enrollment = EstudianteCarrera::query()->create([
            'estudiante_id' => User::factory()->create()->id, 'coordinador_carrera_id' => $assignment->id,
        ]);
        $this->patchJson("/api/v1/admin/users/{$coordinator->id}", ['nombres_completos' => 'No debe guardarse', 'carrera_ids' => []])
            ->assertStatus(409);
        $this->assertSame('Nombre original', $coordinator->refresh()->nombres_completos);
        $this->assertModelExists($assignment);
        $this->assertModelExists($enrollment);
    }

    public function test_inactive_careers_or_faculties_reject_new_assignments_but_preserve_existing_ones(): void
    {
        $this->authenticateAdministrator();
        $coordinator = User::factory()->create();
        $coordinator->assignRole('coordinador');
        $faculty = Facultad::query()->create(['nombre' => 'Facultad inactiva', 'activa' => false]);
        $inactive = Carrera::query()->create(['nombre' => 'Carrera inactiva', 'activa' => false]);
        $blocked = Carrera::query()->create(['nombre' => 'Facultad desactivada', 'facultad_id' => $faculty->id, 'activa' => true]);
        $active = Carrera::query()->create(['nombre' => 'Carrera activa', 'activa' => true]);
        foreach ([$inactive, $blocked] as $career) {
            $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", ['carrera_ids' => [$active->id, $career->id]])
                ->assertUnprocessable()->assertJsonValidationErrors('carrera_ids');
        }
        $this->assertDatabaseMissing('coordinador_carreras', ['coordinador_id' => $coordinator->id]);
        $coordinator->carrerasCoordinadas()->sync([$inactive->id, $blocked->id]);
        $this->putJson("/api/v1/admin/coordinators/{$coordinator->id}/careers", ['carrera_ids' => [$inactive->id, $blocked->id, $active->id]])
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_career_requires_faculty_and_modalities_and_preserves_inactive_associations_when_edited(): void
    {
        $this->authenticateAdministrator();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad activa', 'activa' => true]);
        $modality = Modalidad::query()->create(['nombre' => 'Modalidad para edición', 'activa' => true]);
        $body = ['nombre' => 'Software', 'activa' => true, 'facultad_id' => $faculty->id, 'modalidad_ids' => [$modality->id]];
        $this->postJson('/api/v1/admin/catalogs/carreras', [...$body, 'modalidad_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('modalidad_ids');
        $this->postJson('/api/v1/admin/catalogs/carreras', [...$body, 'facultad_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('facultad_id');
        $id = $this->postJson('/api/v1/admin/catalogs/carreras', $body)->assertCreated()->json('data.id');
        $faculty->update(['activa' => false]);
        $modality->update(['activa' => false]);
        $this->putJson("/api/v1/admin/catalogs/carreras/{$id}", [...$body, 'nombre' => 'Software actualizado'])
            ->assertOk()->assertJsonPath('data.modalidades.0.id', $modality->id);
        $this->postJson('/api/v1/admin/catalogs/carreras', [...$body, 'nombre' => 'Nueva carrera'])
            ->assertUnprocessable()->assertJsonValidationErrors(['facultad_id', 'modalidad_ids.0']);
        $otherFaculty = Facultad::query()->create(['nombre' => 'Otra facultad inactiva', 'activa' => false]);
        $otherModality = Modalidad::query()->create(['nombre' => 'Otra modalidad inactiva', 'activa' => false]);
        $this->putJson("/api/v1/admin/catalogs/carreras/{$id}", [...$body, 'facultad_id' => $otherFaculty->id, 'modalidad_ids' => [$otherModality->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['facultad_id', 'modalidad_ids.0']);
        $this->assertDatabaseHas('carreras', ['id' => $id, 'facultad_id' => $faculty->id]);
    }

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
            $this->assertContains($exception->errorInfo[0], ['23503', '23001']);
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
