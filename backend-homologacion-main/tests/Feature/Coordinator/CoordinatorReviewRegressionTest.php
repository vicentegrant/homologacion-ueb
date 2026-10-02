<?php

namespace Tests\Feature\Coordinator;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CoordinatorReviewRegressionTest extends CoordinatorWorkflowTestCase
{
    public function test_can_observe_two_documents_in_the_same_review(): void
    {
        $context = $this->scenario();
        $first = $context['solicitud']->documentos()->firstOrFail();
        $requirement = $first->documentoRequerido->replicate();
        $requirement->nombre_documento = 'Segundo requisito';
        $requirement->save();
        $second = $first->replicate();
        $second->documento_requerido_proceso_id = $requirement->id;
        $second->save();

        $this->patchJson('/api/v1/coordinator/documents/'.$first->id.'/review', ['estado' => 'observado', 'observacion' => 'Corregir primero.'])->assertOk();
        $this->patchJson('/api/v1/coordinator/documents/'.$second->id.'/review', ['estado' => 'observado', 'observacion' => 'Corregir segundo.'])->assertOk();
        $this->assertSame('observado', $second->fresh()->estadoDocumento->nombre);
        $this->assertSame(2, $context['solicitud']->historialEstados()->count());
    }

    public function test_replacement_requires_new_verification_before_processing(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();
        $context['student']->antecedentesAcademicos()->create([
            'universidad_origen' => 'Universidad', 'carrera_origen' => 'Sistemas',
            'tipo_institucion' => 'publica', 'periodo_cursado' => '2025',
        ]);
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertOk();
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'observado', 'observacion' => 'Corregir archivo.'])->assertOk();
        $this->asUser($context['student']);
        $this->post('/api/v1/student/solicitudes/'.$context['solicitud']->id.'/documentos/'.$document->id, [
            'archivo' => UploadedFile::fake()->createWithContent('nuevo.pdf', "%PDF-1.4\nnuevo"),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/v1/student/solicitudes/'.$context['solicitud']->id.'/enviar')->assertOk();
        $this->asUser($context['coordinator']);
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])->assertOk();

        $this->assertSame('en_revision', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->assertSame(0, $document->verificaciones()->count());
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertOk();
        $this->assertSame('en_proceso', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
    }

    public function test_returns_409_when_verifying_a_document_without_a_stored_file(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();
        Storage::disk('local')->delete($document->ruta_documento_oficio);

        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertStatus(409);

        $this->assertSame(0, $document->verificaciones()->count());
    }

    public function test_returns_409_for_review_and_verification_after_processing_starts(): void
    {
        $context = $this->scenario('en_proceso');
        $document = $context['solicitud']->documentos()->firstOrFail();

        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'observado', 'observacion' => 'Fuera de etapa.'])->assertStatus(409);
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertStatus(409);

        $this->assertSame('presentado', $document->fresh()->estadoDocumento->nombre);
        $this->assertSame(0, $document->observaciones()->count());
        $this->assertSame(0, $document->verificaciones()->count());
    }
}
