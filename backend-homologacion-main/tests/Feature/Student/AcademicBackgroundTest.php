<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AcademicBackgroundTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_creates_background_owned_by_authenticated_user(): void
    {
        $student = $this->authenticateStudent();
        $other = User::factory()->create();

        $response = $this->postJson('/api/v1/student/antecedentes', [
            ...$this->validPayload(), 'estudiante_id' => $other->id,
        ])->assertCreated()->assertJsonPath('data.universidad_origen', 'Universidad de origen');

        $this->assertDatabaseHas('antecedentes_academicos', [
            'id' => $response->json('data.id'), 'estudiante_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('antecedentes_academicos', ['estudiante_id' => $other->id]);
    }

    public function test_list_is_paginated_and_only_includes_own_backgrounds(): void
    {
        $student = $this->authenticateStudent();
        User::factory()->create()->antecedentesAcademicos()->create($this->validPayload());
        $student->antecedentesAcademicos()->create($this->validPayload());
        $latest = $student->antecedentesAcademicos()->create($this->validPayload());

        $this->getJson('/api/v1/student/antecedentes?per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $latest->id);
    }

    public function test_student_can_view_and_partially_update_own_background(): void
    {
        $student = $this->authenticateStudent();
        $record = $student->antecedentesAcademicos()->create($this->validPayload());
        $other = User::factory()->create();

        $this->getJson("/api/v1/student/antecedentes/{$record->id}")->assertOk()->assertJsonPath('data.id', $record->id);
        $this->patchJson("/api/v1/student/antecedentes/{$record->id}", [
            'carrera_origen' => 'Ingeniería de Software', 'estudiante_id' => $other->id,
        ])->assertOk()->assertJsonPath('data.carrera_origen', 'Ingeniería de Software');

        $this->assertDatabaseHas('antecedentes_academicos', [
            'id' => $record->id, 'estudiante_id' => $student->id,
            'carrera_origen' => 'Ingeniería de Software', 'universidad_origen' => 'Universidad de origen',
        ]);
    }

    public function test_other_students_backgrounds_return_404_and_cannot_be_modified(): void
    {
        $this->authenticateStudent();
        $other = User::factory()->create();
        $record = $other->antecedentesAcademicos()->create($this->validPayload());

        $this->getJson("/api/v1/student/antecedentes/{$record->id}")->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Recurso no encontrado.']);
        $this->patchJson("/api/v1/student/antecedentes/{$record->id}", ['carrera_origen' => 'No permitido'])
            ->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Recurso no encontrado.']);
        $this->getJson('/api/v1/student/antecedentes/999999')->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Recurso no encontrado.']);

        $this->assertSame('Sistemas', $record->fresh()->carrera_origen);
    }

    public function test_background_requires_all_fields_and_limits_their_lengths(): void
    {
        $this->authenticateStudent();

        $this->postJson('/api/v1/student/antecedentes', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['universidad_origen', 'carrera_origen', 'tipo_institucion', 'periodo_cursado']);
        $this->postJson('/api/v1/student/antecedentes', [
            'universidad_origen' => str_repeat('a', 256), 'carrera_origen' => str_repeat('a', 256),
            'tipo_institucion' => str_repeat('a', 101), 'periodo_cursado' => str_repeat('a', 101),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['universidad_origen', 'carrera_origen', 'tipo_institucion', 'periodo_cursado']);

        $this->assertDatabaseCount('antecedentes_academicos', 0);
    }

    public function test_invalid_update_preserves_existing_background(): void
    {
        $student = $this->authenticateStudent();
        $record = $student->antecedentesAcademicos()->create($this->validPayload());

        $this->patchJson("/api/v1/student/antecedentes/{$record->id}", [
            'universidad_origen' => null, 'carrera_origen' => '',
            'tipo_institucion' => [], 'periodo_cursado' => str_repeat('a', 101),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['universidad_origen', 'carrera_origen', 'tipo_institucion', 'periodo_cursado']);

        $this->assertSame('Sistemas', $record->fresh()->carrera_origen);
    }

    public function test_background_endpoints_require_authentication_and_student_role(): void
    {
        $this->getJson('/api/v1/student/antecedentes')->assertUnauthorized();
        $this->postJson('/api/v1/student/antecedentes', $this->validPayload())->assertUnauthorized();
        $this->getJson('/api/v1/student/antecedentes/1')->assertUnauthorized();
        $this->patchJson('/api/v1/student/antecedentes/1', $this->validPayload())->assertUnauthorized();
        $this->seed(RoleSeeder::class);

        foreach (['Administrador', 'Coordinador'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            Auth::forgetGuards();
            $this->withToken($user->createToken('test')->plainTextToken);
            $this->getJson('/api/v1/student/antecedentes')->assertForbidden();
            $this->postJson('/api/v1/student/antecedentes', $this->validPayload())->assertForbidden();
            $this->getJson('/api/v1/student/antecedentes/1')->assertForbidden();
            $this->patchJson('/api/v1/student/antecedentes/1', $this->validPayload())->assertForbidden();
        }

        $this->assertDatabaseCount('antecedentes_academicos', 0);
    }

    public function test_inactive_student_cannot_create_backgrounds(): void
    {
        $student = $this->authenticateStudent();
        $student->update(['cuenta_activa' => false]);

        $this->postJson('/api/v1/student/antecedentes', $this->validPayload())->assertForbidden();

        $this->assertDatabaseCount('antecedentes_academicos', 0);
    }

    public function test_background_pagination_rejects_an_excessive_page_size(): void
    {
        $this->authenticateStudent();

        $this->getJson('/api/v1/student/antecedentes?per_page=101')
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    private function authenticateStudent(): User
    {
        $this->seed(RoleSeeder::class);
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $this->withToken($student->createToken('test')->plainTextToken);

        return $student;
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'universidad_origen' => 'Universidad de origen', 'carrera_origen' => 'Sistemas',
            'tipo_institucion' => 'publica', 'periodo_cursado' => '2024-2025',
        ];
    }
}
