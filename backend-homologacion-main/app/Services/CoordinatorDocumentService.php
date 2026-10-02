<?php

namespace App\Services;

use App\Models\EstadoDocumento;
use App\Models\SolicitudDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CoordinatorDocumentService
{
    public function __construct(
        private CoordinatorAccessService $access,
        private SolicitudWorkflowService $workflow,
    ) {}

    public function review(User $coordinator, int $id, string $state, ?string $observation): SolicitudDocumento
    {
        $document = DB::transaction(function () use ($coordinator, $id, $state, $observation): SolicitudDocumento {
            $document = $this->lockedDocument($coordinator, $id);
            $current = $this->workflow->currentState($document->solicitud);
            abort_unless(in_array($current, ['en_revision', 'observado'], true), 409, 'La solicitud no está en revisión documental.');
            abort_unless($document->ruta_documento_oficio !== null && Storage::disk('local')->exists($document->ruta_documento_oficio), 409, 'El documento aún no ha sido presentado o su archivo no está disponible.');

            $document->update([
                'estado_documento_id' => EstadoDocumento::query()->where('nombre', $state)->firstOrFail()->id,
                'validez' => $state === 'aprobado',
            ]);

            if ($state === 'observado') {
                $document->verificaciones()->delete();
                $document->observaciones()->create(['observacion' => $observation]);
                if ($current === 'en_revision') {
                    $this->workflow->transition($document->solicitud, 'observado', $coordinator, 'Se observaron documentos de la solicitud.');
                }
            } else {
                $this->workflow->advanceToProcessingWhenReady($document->solicitud, $coordinator);
            }

            return $document;
        });

        return $document->load(['documentoRequerido', 'estadoDocumento', 'observaciones', 'verificaciones.coordinador']);
    }

    public function verify(User $coordinator, int $id, bool $approved): SolicitudDocumento
    {
        $document = DB::transaction(function () use ($coordinator, $id, $approved): SolicitudDocumento {
            $document = $this->lockedDocument($coordinator, $id);
            abort_unless(in_array($this->workflow->currentState($document->solicitud), ['en_revision', 'observado'], true), 409, 'La solicitud no está en revisión documental.');
            abort_unless(in_array($document->estadoDocumento->nombre, ['presentado', 'aprobado'], true)
                && $document->ruta_documento_oficio !== null
                && Storage::disk('local')->exists($document->ruta_documento_oficio), 409, 'Debe presentarse un archivo válido antes de verificarlo.');
            $document->verificaciones()->updateOrCreate(
                ['coordinador_id' => $coordinator->id],
                ['estado' => $approved],
            );
            if ($approved) {
                $this->workflow->advanceToProcessingWhenReady($document->solicitud, $coordinator);
            }

            return $document;
        });

        return $document->load(['documentoRequerido', 'estadoDocumento', 'observaciones', 'verificaciones.coordinador']);
    }

    private function lockedDocument(User $coordinator, int $id): SolicitudDocumento
    {
        $document = $this->access->document($coordinator, $id);
        $solicitud = $this->access->solicitud($coordinator, $document->solicitud_id, true);

        return $solicitud->documentos()->lockForUpdate()->findOrFail($id)->setRelation('solicitud', $solicitud);
    }
}
