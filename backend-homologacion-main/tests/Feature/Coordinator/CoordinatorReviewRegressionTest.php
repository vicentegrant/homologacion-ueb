<?php

namespace Tests\Feature\Coordinator;

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
        $url = '/api/v1/coordinator/documents/'.$document->id.'/review';
        $document->verificaciones()->create(['coordinador_id' => $context['coordinator']->id, 'estado' => true]);
        $this->patchJson($url, ['estado' => 'observado', 'observacion' => 'Falta una firma.'])->assertOk();
        $this->assertSame(0, $document->verificaciones()->count());
        $this->patchJson($url, ['estado' => 'aprobado'])->assertStatus(409);
        $this->patchJson($url, ['estado' => 'presentado'])->assertOk();
        $this->assertSame(0, $document->verificaciones()->count());
        $this->assertSame('en_revision', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->patchJson($url, ['estado' => 'aprobado'])->assertOk();
        $this->assertSame(1, $document->verificaciones()->count());
        $this->assertSame('en_proceso', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
    }

    public function test_separate_digital_verification_endpoint_is_retired(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();
        Storage::disk('local')->delete($document->ruta_documento_oficio);

        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertStatus(410);

        $this->assertSame(0, $document->verificaciones()->count());
    }

    public function test_returns_409_for_review_and_verification_after_processing_starts(): void
    {
        $context = $this->scenario('en_proceso');
        $document = $context['solicitud']->documentos()->firstOrFail();

        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'observado', 'observacion' => 'Fuera de etapa.'])->assertStatus(409);
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertStatus(410);

        $this->assertSame('presentado', $document->fresh()->estadoDocumento->nombre);
        $this->assertSame(0, $document->observaciones()->count());
        $this->assertSame(0, $document->verificaciones()->count());
    }
}
