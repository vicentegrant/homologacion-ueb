<?php

namespace Tests\Feature\Student;

use App\Models\Carrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\User;

class SolicitudWorkflowTest extends StudentWorkflowTestCase
{
    public function test_creation_snapshots_career_and_requirements_and_ignores_privileged_fields(): void
    {
        $context = $this->scenario();
        $global = DocumentoRequeridoProceso::query()->create([
            'tramite_proceso_id' => $context['process']->id, 'nombre_documento' => 'Identidad',
        ]);
        $otherCareer = Carrera::query()->create(['nombre' => 'Otra']);
        DocumentoRequeridoProceso::query()->create([
            'tramite_proceso_id' => $context['process']->id, 'carrera_id' => $otherCareer->id, 'nombre_documento' => 'Ajeno',
        ]);

        $response = $this->postJson('/api/v1/student/solicitudes', [
            ...$this->payload($context), 'estudiante_id' => $context['coordinator']->id,
            'coordinador_id' => $context['student']->id, 'carrera_id' => $otherCareer->id, 'estado' => 'listo',
        ])->assertCreated()->assertJsonPath('data.estado_actual', 'pendiente')
            ->assertJsonCount(2, 'data.documentos')->assertJsonPath('data.carrera.id', $context['career']->id)
            ->assertJsonMissingPath('data.documentos.0.requisito.ruta_ejemplo');

        $this->assertDatabaseHas('solicitudes', ['id' => $response->json('data.id'),
            'estudiante_id' => $context['student']->id, 'coordinador_id' => $context['coordinator']->id]);
        $this->assertDatabaseHas('solicitud_documentos', ['solicitud_id' => $response->json('data.id'), 'documento_requerido_proceso_id' => $global->id]);
        $this->assertDatabaseCount('historial_estados_solicitud', 1);
    }

    public function test_creation_rejects_duplicate_active_requests_without_partial_records(): void
    {
        $context = $this->scenario();
        $this->draft($context);

        $this->postJson('/api/v1/student/solicitudes', $this->payload($context))
            ->assertStatus(409)->assertJsonPath('success', false);

        $this->assertDatabaseCount('solicitudes', 1);
        $this->assertDatabaseCount('solicitud_documentos', 1);
    }

    public function test_student_cannot_use_another_students_assignment(): void
    {
        $context = $this->scenario();
        $other = User::factory()->create();
        $other->assignRole('Estudiante');
        $this->asUser($other);

        $this->postJson('/api/v1/student/solicitudes', $this->payload($context))->assertNotFound();

        $this->assertDatabaseCount('solicitudes', 0);
    }

    public function test_missing_requirements_and_inactive_coordinator_block_creation(): void
    {
        $context = $this->scenario();
        $context['coordinator']->update(['cuenta_activa' => false]);
        $this->postJson('/api/v1/student/solicitudes', $this->payload($context))->assertStatus(409);
        $context['coordinator']->update(['cuenta_activa' => true]);
        $context['requirement']->delete();

        $this->postJson('/api/v1/student/solicitudes', $this->payload($context))->assertStatus(409);

        $this->assertDatabaseCount('solicitudes', 0);
        $this->assertDatabaseCount('historial_estados_solicitud', 0);
    }

    public function test_creation_validates_required_fields_and_unknown_process(): void
    {
        $context = $this->scenario();

        $this->postJson('/api/v1/student/solicitudes', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['coordinador_carrera_id', 'tramite_proceso_id', 'procedencia_estudios']);
        $this->postJson('/api/v1/student/solicitudes', [...$this->payload($context), 'tramite_proceso_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('tramite_proceso_id');

        $this->assertDatabaseCount('solicitudes', 0);
    }

    public function test_only_pending_metadata_is_editable_and_career_cannot_change(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);

        $this->patchJson("/api/v1/student/solicitudes/{$solicitud->id}", [
            'procedencia_estudios' => 'Corregida', 'carrera_id' => 99999, 'coordinador_id' => null,
        ])->assertOk()->assertJsonPath('data.procedencia_estudios', 'Corregida');
        $this->assertSame($context['career']->id, $solicitud->fresh()->carrera_id);
        $this->state($solicitud, 'en_revision');
        $this->patchJson("/api/v1/student/solicitudes/{$solicitud->id}", ['procedencia_estudios' => 'No permitido'])->assertStatus(409);
        $this->assertSame('Corregida', $solicitud->fresh()->procedencia_estudios);
    }

    public function test_submission_requires_documents_and_academic_background(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);

        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")
            ->assertUnprocessable()->assertJsonValidationErrors('documentos');
        $this->upload($solicitud);
        $context['student']->antecedentesAcademicos()->delete();
        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")
            ->assertUnprocessable()->assertJsonValidationErrors('antecedentes');

        $this->assertDatabaseCount('historial_estados_solicitud', 1);
    }

    public function test_complete_request_enters_review_once_and_preserves_history(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);

        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")
            ->assertOk()->assertJsonPath('data.estado_actual', 'en_revision')->assertJsonCount(2, 'data.historial_estados');
        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")->assertStatus(409);

        $this->assertDatabaseCount('historial_estados_solicitud', 2);
    }

    public function test_listing_and_filters_only_expose_own_requests(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->state($solicitud, 'en_revision');

        $this->getJson('/api/v1/student/solicitudes?estado=en_revision&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $solicitud->id);
        $this->getJson('/api/v1/student/solicitudes?estado=pendiente')->assertOk()->assertJsonPath('meta.total', 0);
        $other = User::factory()->create();
        $other->assignRole('Estudiante');
        $this->asUser($other);
        $this->getJson('/api/v1/student/solicitudes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}")->assertNotFound();
        $this->patchJson("/api/v1/student/solicitudes/{$solicitud->id}", ['procedencia_estudios' => 'Ajena'])->assertNotFound();
        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")->assertNotFound();
    }

    public function test_catalog_only_returns_assigned_careers(): void
    {
        $context = $this->scenario();

        $this->getJson('/api/v1/student/catalogo')->assertOk()->assertJsonCount(3, 'data.tramites')
            ->assertJsonPath('data.asignaciones.0.coordinador_carrera_id', $context['assignment']->id);
        $other = User::factory()->create();
        $other->assignRole('Estudiante');
        $this->asUser($other);
        $this->getJson('/api/v1/student/catalogo')->assertOk()->assertJsonCount(0, 'data.asignaciones');
    }

    public function test_new_requirements_do_not_change_an_existing_draft(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        DocumentoRequeridoProceso::query()->create([
            'tramite_proceso_id' => $context['process']->id, 'nombre_documento' => 'Nuevo requisito',
        ]);
        $this->upload($solicitud);

        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")
            ->assertOk()->assertJsonCount(1, 'data.documentos');
    }

    public function test_submission_is_blocked_if_assigned_coordinator_becomes_inactive(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);
        $context['coordinator']->update(['cuenta_activa' => false]);

        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")->assertStatus(409);

        $this->assertDatabaseCount('historial_estados_solicitud', 1);
    }

    public function test_admin_career_filter_uses_recorded_career_instead_of_current_enrollment(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $otherCareer = Carrera::query()->create(['nombre' => 'Telecomunicaciones']);
        $context['assignment']->update(['carrera_id' => $otherCareer->id]);
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        $this->asUser($admin);

        $this->getJson('/api/v1/admin/solicitudes?carrera='.$otherCareer->id)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/admin/solicitudes?carrera='.$context['career']->id)
            ->assertOk()->assertJsonPath('data.0.id', $solicitud->id)
            ->assertJsonPath('data.0.carrera.id', $context['career']->id);
    }
}
