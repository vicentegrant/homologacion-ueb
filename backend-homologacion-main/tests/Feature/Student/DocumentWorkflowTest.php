<?php

namespace Tests\Feature\Student;

use App\Models\EstadoDocumento;
use App\Models\SolicitudDocumento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentWorkflowTest extends StudentWorkflowTestCase
{
    public function test_historical_pdf_is_private_and_download_returns_original_content(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);
        $document = $solicitud->documentos()->firstOrFail();

        Storage::disk('local')->assertExists($document->ruta_documento_oficio);
        $this->assertFalse($document->validez);
        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}")->assertOk()
            ->assertJsonMissingPath('data.documentos.0.ruta_documento_oficio')
            ->assertJsonMissingPath('data.documentos.0.requisito.ruta_ejemplo');
        $this->get("/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}/download")
            ->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertStreamedContent("%PDF-1.4\nnotas");
    }

    public function test_legacy_storage_service_replacement_preserves_file_integrity(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);
        $document = $solicitud->documentos()->firstOrFail();
        $oldPath = $document->ruta_documento_oficio;

        $this->upload($solicitud);

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->fresh()->ruta_documento_oficio);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('solicitud_documentos', 1);
    }

    public function test_invalid_and_oversized_uploads_leave_no_files_or_changes(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $document = $solicitud->documentos()->firstOrFail();
        $url = "/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}";

        $source = UploadedFile::fake()->createWithContent('false.pdf', 'not a pdf');
        $disguised = new UploadedFile($source->getPathname(), 'false.pdf', 'application/pdf', null, true);
        $this->post($url, ['archivo' => $disguised], ['Accept' => 'application/json'])
            ->assertStatus(410);
        $this->post($url, ['archivo' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(410);
        $this->postJson($url, [])->assertStatus(410);

        $this->assertNull($document->fresh()->ruta_documento_oficio);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_review_and_terminal_states_do_not_allow_document_changes(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $document = $solicitud->documentos()->firstOrFail();

        foreach (['en_revision', 'en_proceso', 'aprobado', 'en_consejo', 'listo', 'rechazado'] as $state) {
            $this->state($solicitud, $state);
            $this->post("/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}", [
                'archivo' => UploadedFile::fake()->createWithContent('notas.pdf', "%PDF-1.4\nnotas"),
            ], ['Accept' => 'application/json'])->assertStatus(410);
        }

        $this->assertNull($document->fresh()->ruta_documento_oficio);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_presential_corrections_preserve_observations_without_student_resubmission(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $document = $solicitud->documentos()->firstOrFail();
        $this->asUser($context['coordinator']);
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'observado', 'observacion' => 'Adjunte una copia legible.'])->assertOk();
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'presentado'])->assertOk();
        $this->asUser($context['student']);
        $this->getJson('/api/v1/student/solicitudes/'.$solicitud->id)->assertOk()->assertJsonPath('data.estado_actual', 'en_revision')->assertJsonPath('data.documentos.0.observaciones.0.observacion', 'Adjunte una copia legible.');
        $this->postJson('/api/v1/student/solicitudes/'.$solicitud->id.'/enviar')->assertStatus(410);
        $this->assertFalse($document->refresh()->validez);
    }

    public function test_approved_document_cannot_be_replaced_during_corrections(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);
        $document = $solicitud->documentos()->firstOrFail();
        $document->update(['estado_documento_id' => EstadoDocumento::query()->where('nombre', 'aprobado')->firstOrFail()->id, 'validez' => true]);
        $oldPath = $document->ruta_documento_oficio;
        $this->state($solicitud, 'observado');

        $this->post("/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}", [
            'archivo' => UploadedFile::fake()->createWithContent('notas.pdf', "%PDF-1.4\nnotas"),
        ], ['Accept' => 'application/json'])->assertStatus(410);

        $this->assertSame($oldPath, $document->fresh()->ruta_documento_oficio);
        $this->assertTrue($document->fresh()->validez);
    }

    public function test_documents_are_scoped_to_owner_and_parent_request(): void
    {
        $context = $this->scenario();
        $first = $this->draft($context);
        $this->upload($first);
        $document = $first->documentos()->firstOrFail();
        $this->state($first, 'rechazado');
        $second = $this->draft($context);
        $this->getJson("/api/v1/student/solicitudes/{$second->id}/documentos/{$document->id}/download")->assertNotFound();

        $other = User::factory()->create();
        $other->assignRole('Estudiante');
        $this->asUser($other);
        $this->getJson("/api/v1/student/solicitudes/{$first->id}/documentos/{$document->id}/download")->assertNotFound();
        $this->post("/api/v1/student/solicitudes/{$first->id}/documentos/{$document->id}", [
            'archivo' => UploadedFile::fake()->createWithContent('notas.pdf', "%PDF-1.4\nnotas"),
        ], ['Accept' => 'application/json'])->assertNotFound();

        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_missing_file_returns_404_and_blocks_submission(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);
        $document = $solicitud->documentos()->firstOrFail();
        Storage::disk('local')->delete($document->ruta_documento_oficio);

        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}/download")->assertNotFound();
        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")
            ->assertStatus(410);
    }

    public function test_disabled_upload_endpoint_does_not_touch_database_or_historical_files(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);
        $document = $solicitud->documentos()->firstOrFail();
        $oldPath = $document->ruta_documento_oficio;
        $originalDispatcher = SolicitudDocumento::getEventDispatcher();
        $dispatcher = clone $originalDispatcher;
        SolicitudDocumento::setEventDispatcher($dispatcher);
        SolicitudDocumento::updating(function (): void {
            throw new RuntimeException('Simulated persistence failure');
        });

        try {
            $this->post("/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}", [
                'archivo' => UploadedFile::fake()->createWithContent('notas.pdf', "%PDF-1.4\nreemplazo"),
            ], ['Accept' => 'application/json'])->assertStatus(410);
        } finally {
            SolicitudDocumento::setEventDispatcher($originalDispatcher);
        }

        $this->assertSame($oldPath, $document->fresh()->ruta_documento_oficio);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }
}
