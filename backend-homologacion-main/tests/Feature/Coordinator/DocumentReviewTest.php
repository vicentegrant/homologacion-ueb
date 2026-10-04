<?php

namespace Tests\Feature\Coordinator;

class DocumentReviewTest extends CoordinatorWorkflowTestCase
{
    public function test_presential_validation_atomically_verifies_and_advances_to_processing(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();

        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])
            ->assertOk()->assertJsonPath('data.estado', 'aprobado')->assertJsonPath('data.validez', true);
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])
            ->assertStatus(410);

        $this->assertDatabaseHas('verificaciones_documento', [
            'solicitud_documento_id' => $document->id,
            'coordinador_id' => $context['coordinator']->id,
            'estado' => true,
        ]);
        $this->assertSame('en_proceso', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->assertSame($context['coordinator']->id, $context['solicitud']->fresh()->ultimoHistorialEstado->usuario_responsable_id);
    }

    public function test_observation_is_required_persisted_notified_and_visible_after_student_correction(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();
        $url = '/api/v1/coordinator/documents/'.$document->id.'/review';
        $this->patchJson($url, ['estado' => 'observado'])->assertUnprocessable()->assertJsonValidationErrors('observacion');
        $this->patchJson($url, ['estado' => 'observado', 'observacion' => 'El documento no es legible.'])->assertOk();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $context['student']->id]);
        $this->patchJson($url, ['estado' => 'presentado'])->assertOk();
        $this->asUser($context['student']);
        $this->getJson('/api/v1/student/solicitudes/'.$context['solicitud']->id)->assertOk()->assertJsonPath('data.estado_actual', 'en_revision')->assertJsonPath('data.documentos.0.observaciones.0.observacion', 'El documento no es legible.');
    }

    public function test_document_access_is_scoped_to_coordinator_careers_for_read_review_and_download(): void
    {
        $context = $this->scenario();
        $document = $context['otherSolicitud']->documentos()->firstOrFail();

        $this->getJson('/api/v1/coordinator/documents/'.$document->id)->assertNotFound();
        $this->getJson('/api/v1/coordinator/documents/'.$document->id.'/download')->assertNotFound();
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])->assertNotFound();
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertNotFound();

        $this->assertFalse($document->fresh()->validez);
    }
}
