<?php

namespace App\Http\Resources\Api;

use App\Models\ResolucionSolicitud;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ResolucionSolicitud */
class ResolucionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'solicitud_id' => $this->resource->solicitud_id,
            'numero_resolucion' => $this->resource->numero_resolucion,
            'fecha_aprobacion' => $this->resource->fecha_aprobacion?->toDateString(),
            'registrado_por' => $this->resource->coordinador === null ? null : [
                'id' => $this->resource->coordinador->getKey(),
                'nombres_completos' => $this->resource->coordinador->nombres_completos,
            ],
            'download_url' => route(
                $request->user()?->hasRole('coordinador') ? 'coordinator.solicitudes.resolucion.download' : 'admin.solicitudes.resolucion.download',
                $this->resource->solicitud_id,
                false,
            ),
            'created_at' => $this->resource->created_at,
        ];
    }
}
