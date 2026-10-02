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
    public function test_pdf_upload_is_private_and_download_returns_the_original_content(): void
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

    public function test_replacement_removes_previous_file_after_persistence(): void
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
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->post($url, ['archivo' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('archivo');

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
            ], ['Accept' => 'application/json'])->assertStatus(409);
        }

        $this->assertNull($document->fresh()->ruta_documento_oficio);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_observed_documents_can_be_corrected_and_resubmitted_without_losing_observations(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->upload($solicitud);
        $document = $solicitud->documentos()->firstOrFail();
        $document->update(['estado_documento_id' => EstadoDocumento::query()->where('nombre', 'observado')->firstOrFail()->id]);
        $observation = $document->observaciones()->create(['observacion' => 'Adjunte una copia legible.']);
        $this->state($solicitud, 'observado');

        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")->assertUnprocessable();
        $this->upload($solicitud);
        $this->postJson("/api/v1/student/solicitudes/{$solicitud->id}/enviar")->assertOk()
            ->assertJsonPath('data.estado_actual', 'en_revision')
            ->assertJsonPath('data.documentos.0.estado', 'presentado')
            ->assertJsonPath('data.documentos.0.observaciones.0.observacion', 'Adjunte una copia legible.');

        $this->assertModelExists($observation);
        $this->assertFalse($document->fresh()->validez);
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
        ], ['Accept' => 'application/json'])->assertStatus(409);

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
            ->assertUnprocessable()->assertJsonValidationErrors('documentos');
    }

    public function test_database_failure_preserves_previous_file_and_removes_new_upload(): void
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
            ], ['Accept' => 'application/json'])->assertStatus(500);
        } finally {
            SolicitudDocumento::setEventDispatcher($originalDispatcher);
        }

        $this->assertSame($oldPath, $document->fresh()->ruta_documento_oficio);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }
}
