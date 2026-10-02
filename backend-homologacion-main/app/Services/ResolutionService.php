<?php

namespace App\Services;

use App\Models\ResolucionSolicitud;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ResolutionService
{
    public function __construct(private SolicitudWorkflowService $workflow) {}

    /** @param array{numero_resolucion: string, fecha_aprobacion: string, archivo: UploadedFile} $data */
    public function create(Solicitud $solicitud, User $actor, array $data): ResolucionSolicitud
    {
        $path = $data['archivo']->store('resoluciones', 'local');

        if (! is_string($path)) {
            throw new RuntimeException('No fue posible almacenar el archivo de resolución.');
        }

        try {
            return DB::transaction(function () use ($solicitud, $actor, $data, $path): ResolucionSolicitud {
                $solicitud = Solicitud::query()->lockForUpdate()->findOrFail($solicitud->id);
                abort_if($solicitud->resolucion()->exists(), 409, 'La solicitud ya tiene una resolución registrada.');
                abort_unless($this->workflow->currentState($solicitud) === 'en_consejo', 409, 'La solicitud no está pendiente de resolución del Consejo.');
                $resolution = ResolucionSolicitud::query()->create([
                    'solicitud_id' => $solicitud->getKey(),
                    'coordinador_id' => $actor->getKey(),
                    'numero_resolucion' => $data['numero_resolucion'],
                    'fecha_aprobacion' => $data['fecha_aprobacion'],
                    'ruta_archivo' => $path,
                ]);
                $this->workflow->transition($solicitud, 'listo', $actor, 'Resolución externa registrada: '.$resolution->numero_resolucion.'.');

                return $resolution;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
