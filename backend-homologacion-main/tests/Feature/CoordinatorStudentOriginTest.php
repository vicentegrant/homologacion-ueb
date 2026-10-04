<?php

namespace Tests\Feature;

use App\Models\AntecedenteAcademico;
use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Coordinator\CoordinatorWorkflowTestCase;

class CoordinatorStudentOriginTest extends CoordinatorWorkflowTestCase
{
    public function test_internal_origin_uses_ueb_catalog_and_institution_even_from_another_coordination(): void
    {
        Notification::fake();
        $context = $this->scenario();
        $payload = $this->payload($context);
        $id = $this->postJson('/api/v1/coordinator/students', [...$payload,
            'procedencia' => 'interna', 'carrera_origen_id' => $context['otherCareer']->id,
            'universidad_origen' => 'Institución manipulada', 'tipo_institucion' => 'privada',
        ])->assertCreated()->assertJsonPath('data.antecedentes_academicos.0.procedencia', 'interna')->json('data.id');
        $this->assertDatabaseHas('antecedentes_academicos', [
            'estudiante_id' => $id, 'procedencia' => 'interna', 'universidad_origen' => AntecedenteAcademico::UEB_NAME,
            'tipo_institucion' => 'publica', 'carrera_origen_id' => $context['otherCareer']->id, 'carrera_origen' => 'Industrial',
        ]);
        Notification::assertSentTo(User::findOrFail($id), TemporaryPasswordNotification::class);
    }

    public function test_external_career_can_share_destination_name_without_entering_ueb_catalog(): void
    {
        Notification::fake();
        $context = $this->scenario();
        $payload = $this->payload($context);
        $count = Carrera::query()->count();
        $id = $this->postJson('/api/v1/coordinator/students', $payload)->assertCreated()
            ->assertJsonPath('data.antecedentes_academicos.0.carrera_origen', 'Software')
            ->assertJsonPath('data.antecedentes_academicos.0.carrera_origen_id', null)->json('data.id');
        $this->assertDatabaseHas('antecedentes_academicos', [
            'estudiante_id' => $id, 'procedencia' => 'externa', 'universidad_origen' => 'Universidad de Cuenca',
            'carrera_origen' => 'Software', 'carrera_origen_id' => null,
        ]);
        $this->assertSame($count, Carrera::query()->count());
        Notification::assertSentTo(User::findOrFail($id), TemporaryPasswordNotification::class);
    }

    public function test_internal_origin_rejects_same_career_and_inactive_offer_without_creating_account(): void
    {
        $context = $this->scenario();
        $payload = [...$this->payload($context), 'procedencia' => 'interna'];
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'carrera_origen_id' => $context['career']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('carrera_origen_id');
        $context['otherCareer']->update(['activa' => false]);
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'carrera_origen_id' => $context['otherCareer']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('carrera_origen_id');
        $faculty = Facultad::query()->create(['nombre' => 'Facultad cerrada', 'activa' => false]);
        $context['otherCareer']->update(['activa' => true, 'facultad_id' => $faculty->id]);
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'carrera_origen_id' => $context['otherCareer']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('carrera_origen_id');
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_external_registration_validates_origin_destination_and_modality(): void
    {
        $context = $this->scenario();
        $payload = $this->payload($context);
        foreach (['carrera_origen', 'universidad_origen', 'tipo_institucion'] as $field) {
            $this->postJson('/api/v1/coordinator/students', [...$payload, $field => ''])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'procedencia' => 'desconocida'])
            ->assertUnprocessable()->assertJsonValidationErrors('procedencia');
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'universidad_origen' => 'UEB'])
            ->assertUnprocessable()->assertJsonValidationErrors('universidad_origen');
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'carrera_id' => $context['otherCareer']->id])->assertForbidden();
        $otherModality = Modalidad::query()->create(['nombre' => 'Modalidad ajena', 'activa' => true]);
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'modalidad_id' => $otherModality->id])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_origin_can_change_when_no_active_request_and_external_conversion_clears_internal_reference(): void
    {
        Notification::fake();
        $context = $this->scenario();
        $payload = $this->payload($context);
        $id = $this->postJson('/api/v1/coordinator/students', [...$payload,
            'procedencia' => 'interna', 'carrera_origen_id' => $context['otherCareer']->id,
        ])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/coordinator/students/'.$id, $payload)->assertOk()
            ->assertJsonPath('data.antecedentes_academicos.0.procedencia', 'externa')
            ->assertJsonPath('data.antecedentes_academicos.0.carrera_origen_id', null);
        $this->assertSame(1, User::findOrFail($id)->antecedentesAcademicos()->count());
        Notification::assertSentTo(User::findOrFail($id), TemporaryPasswordNotification::class);
    }

    public function test_active_request_blocks_origin_institution_and_provenance_changes_atomically(): void
    {
        $context = $this->scenario();
        $payload = $this->payload($context);
        $student = $context['student'];
        $background = $student->antecedentesAcademicos()->create([
            'procedencia' => 'externa', 'universidad_origen' => 'Universidad de Cuenca',
            'carrera_origen' => 'Software', 'tipo_institucion' => 'publica', 'periodo_cursado' => $payload['periodo_cursado'],
        ]);
        $payload = [...$payload, 'nombres_completos' => $student->nombres_completos,
            'tipo_identificacion' => $student->tipo_identificacion ?? 'cedula', 'cedula' => $student->cedula,
            'email' => $student->email, 'numero_celular' => '0991234567',
        ];
        $this->putJson('/api/v1/coordinator/students/'.$student->id, [...$payload, 'universidad_origen' => 'Otra universidad', 'nombres_completos' => 'No guardar'])
            ->assertStatus(409);
        $this->putJson('/api/v1/coordinator/students/'.$student->id, [...$payload, 'procedencia' => 'interna', 'carrera_origen_id' => $context['otherCareer']->id])
            ->assertStatus(409);
        $this->assertSame('Universidad de Cuenca', $background->fresh()->universidad_origen);
        $this->assertSame($payload['nombres_completos'], $student->fresh()->nombres_completos);
    }

    public function test_catalog_hides_inactive_origins_and_marks_destinations_with_inactive_faculty(): void
    {
        $context = $this->scenario();
        $faculty = Facultad::query()->create(['nombre' => 'Facultad suspendida', 'activa' => false]);
        $context['career']->update(['facultad_id' => $faculty->id]);
        $context['otherCareer']->update(['activa' => false]);
        $response = $this->getJson('/api/v1/coordinator/catalogo')->assertOk()
            ->assertJsonPath('data.universidad_interna', AntecedenteAcademico::UEB_NAME)
            ->assertJsonPath('data.carreras.0.activa', false);
        $origins = $response->json('data.carreras_origen');
        $this->assertNotContains($context['career']->id, array_column($origins, 'id'));
        $this->assertNotContains($context['otherCareer']->id, array_column($origins, 'id'));
    }

    public function test_student_cannot_rewrite_classified_origin_but_can_update_academic_cycle(): void
    {
        $context = $this->scenario();
        $background = $context['student']->antecedentesAcademicos()->create([
            'procedencia' => 'interna', 'carrera_origen_id' => $context['otherCareer']->id,
            'universidad_origen' => AntecedenteAcademico::UEB_NAME, 'carrera_origen' => 'Industrial',
            'tipo_institucion' => 'publica', 'periodo_cursado' => 'Primer ciclo / semestre',
        ]);
        $this->asUser($context['student']);
        foreach (['universidad_origen' => 'Otra universidad', 'carrera_origen' => 'Otra carrera', 'tipo_institucion' => 'privada'] as $field => $value) {
            $this->patchJson('/api/v1/student/antecedentes/'.$background->id, [$field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->patchJson('/api/v1/student/antecedentes/'.$background->id, ['periodo_cursado' => 'Segundo ciclo / semestre'])
            ->assertOk()->assertJsonPath('data.procedencia', 'interna')
            ->assertJsonPath('data.carrera_origen_id', $context['otherCareer']->id);
        $this->assertSame('Industrial', $background->fresh()->carrera_origen);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function payload(array $context): array
    {
        $modality = Modalidad::query()->firstOrFail();
        $context['career']->modalidades()->syncWithoutDetaching([$modality->id]);

        return [
            'nombres_completos' => 'Estudiante homologación', 'tipo_identificacion' => 'cedula', 'cedula' => '0100000009',
            'email' => 'origin-flow@example.test', 'numero_celular' => '0991234567',
            'carrera_id' => $context['career']->id, 'modalidad_id' => $modality->id,
            'procedencia' => 'externa', 'universidad_origen' => 'Universidad de Cuenca', 'tipo_institucion' => 'publica',
            'carrera_origen' => 'Software', 'periodo_cursado' => 'Segundo ciclo / semestre',
        ];
    }
}
