<?php

namespace Tests\Feature\Coordinator;

use Illuminate\Http\UploadedFile;

class DocumentReviewTest extends CoordinatorWorkflowTestCase
{
    public function test_approval_and_positive_verification_advance_request_to_processing(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();

        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])
            ->assertOk()->assertJsonPath('data.estado', 'aprobado')->assertJsonPath('data.validez', true);
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])
            ->assertOk()->assertJsonPath('data.verificaciones.0.estado', true);

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
        $this->patchJson($url, ['estado' => 'observado', 'observacion' => 'El documento no es legible.'])
            ->assertOk()->assertJsonPath('data.estado', 'observado');

        $this->assertDatabaseHas('observaciones_documentacion', [
            'solicitud_documento_id' => $document->id,
            'observacion' => 'El documento no es legible.',
        ]);
        $this->assertSame('observado', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $context['student']->id]);

        $context['student']->antecedentesAcademicos()->create([
            'universidad_origen' => 'Universidad', 'carrera_origen' => 'Sistemas',
            'tipo_institucion' => 'publica', 'periodo_cursado' => '2025',
        ]);
        $this->asUser($context['student']);
        $this->post('/api/v1/student/solicitudes/'.$context['solicitud']->id.'/documentos/'.$document->id, [
            'archivo' => UploadedFile::fake()->createWithContent('corregido.pdf', "%PDF-1.4\ncorregido"),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/v1/student/solicitudes/'.$context['solicitud']->id.'/enviar')->assertOk()
            ->assertJsonPath('data.estado_actual', 'en_revision')
            ->assertJsonPath('data.documentos.0.observaciones.0.observacion', 'El documento no es legible.');
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
