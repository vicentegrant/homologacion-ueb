<?php

namespace Tests\Feature\Admin;

use App\Models\AntecedenteAcademico;
use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\EstudianteCarrera;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StudentQueryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_search_and_paginate_students(): void
    {
        $this->authenticateAdministrator();
        $student = User::factory()->create(['nombres_completos' => 'Estudiante Consultable']);
        $student->assignRole('Estudiante');
        $coordinator = User::factory()->create(['nombres_completos' => 'No aparece']);
        $coordinator->assignRole('Coordinador');

        $this->getJson('/api/v1/admin/students?search=Consultable&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_administrator_can_view_student_detail_with_academic_context(): void
    {
        $this->authenticateAdministrator();
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');
        $career = Carrera::query()->create(['nombre' => 'Software']);
        $assignment = CoordinadorCarrera::query()->create([
            'coordinador_id' => $coordinator->id,
            'carrera_id' => $career->id,
        ]);
        EstudianteCarrera::query()->create([
            'estudiante_id' => $student->id,
            'coordinador_carrera_id' => $assignment->id,
        ]);
        AntecedenteAcademico::query()->create([
            'estudiante_id' => $student->id,
            'universidad_origen' => 'Universidad origen',
            'carrera_origen' => 'Sistemas',
            'tipo_institucion' => 'publica',
            'periodo_cursado' => '2024-2025',
        ]);

        $this->getJson("/api/v1/admin/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.carreras.0.nombre', 'Software')
            ->assertJsonPath('data.antecedentes_academicos.0.universidad_origen', 'Universidad origen');
    }

    public function test_non_student_is_not_exposed_through_student_detail(): void
    {
        $this->authenticateAdministrator();
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');

        $this->getJson("/api/v1/admin/students/{$coordinator->id}")->assertNotFound();
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
