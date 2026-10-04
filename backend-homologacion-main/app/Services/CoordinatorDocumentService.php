<?php

namespace App\Services;

use App\Models\EstadoDocumento;
use App\Models\SolicitudDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CoordinatorDocumentService
{
    public function __construct(private CoordinatorAccessService $access, private SolicitudWorkflowService $workflow) {}

    public function review(User $coordinator, int $id, string $state, ?string $observation): SolicitudDocumento
    {
        return DB::transaction(function () use ($coordinator, $id, $state, $observation): SolicitudDocumento {
            $document = $this->access->document($coordinator, $id);
            $solicitud = $this->access->solicitud($coordinator, $document->solicitud_id, true);
            $document = $solicitud->documentos()->lockForUpdate()->findOrFail($id);
            $current = $this->workflow->currentState($solicitud);
            abort_unless(in_array($current, ['pendiente', 'en_revision', 'observado'], true), 409, 'La solicitud ya no admite cambios documentales.');
            abort_unless(in_array($state, ['presentado', 'aprobado', 'observado'], true), 422, 'Estado documental no permitido.');
            abort_if($state === 'observado' && blank($observation), 422, 'Indique el motivo de la observación.');
            if ($state === 'aprobado') {
                abort_unless($document->recibido_at && in_array($document->estadoDocumento->nombre, ['presentado', 'aprobado'], true), 409, 'Registre primero la recepción presencial o la corrección del documento.');
            }
            if ($document->estadoDocumento->nombre === $state && $state !== 'observado' && ($state !== 'presentado' || $document->recibido_at !== null)) {
                return $document->load(['documentoRequerido', 'estadoDocumento', 'observaciones', 'verificaciones.coordinador']);
            }
            $data = ['estado_documento_id' => EstadoDocumento::where('nombre', $state)->firstOrFail()->id, 'validez' => $state === 'aprobado', 'revisado_at' => now(), 'revisado_por_id' => $coordinator->id];
            if ($state === 'presentado') {
                $data['recibido_at'] = now();
                $data['recibido_por_id'] = $coordinator->id;
            }
            $document->update($data);
            $document->verificaciones()->delete();
            if ($state === 'aprobado') {
                $document->verificaciones()->create(['coordinador_id' => $coordinator->id, 'estado' => true]);
            }
            if ($state === 'observado') {
                $document->observaciones()->create(['observacion' => $observation]);
            }
            $document->historial()->create(['usuario_id' => $coordinator->id, 'estado' => $state, 'observacion' => $observation]);
            if ($current === 'pendiente') {
                $this->workflow->transition($solicitud, 'en_revision', $coordinator, 'Inicio de recepción y revisión presencial.');
                $current = 'en_revision';
            }
            $observed = $solicitud->documentos()->where('obligatorio', true)->whereHas('estadoDocumento', fn ($q) => $q->where('nombre', 'observado'))->exists();
            if ($observed && $current === 'en_revision') {
                $this->workflow->transition($solicitud, 'observado', $coordinator, 'Existen requisitos observados; revise el checklist.');
            }
            if (! $observed && $current === 'observado') {
                $this->workflow->transition($solicitud, 'en_revision', $coordinator, 'Correcciones presenciales recibidas. Continúa la revisión.');
            }
            $this->workflow->advanceToProcessingWhenReady($solicitud, $coordinator);

            return $document->load(['documentoRequerido', 'estadoDocumento', 'observaciones', 'verificaciones.coordinador']);
        });
    }

    public function verify(User $coordinator, int $id, bool $approved): SolicitudDocumento
    {
        $this->access->document($coordinator, $id);
        abort(410, 'Use la revisión del checklist presencial; la validación se registra en una sola operación.');
    }
}
