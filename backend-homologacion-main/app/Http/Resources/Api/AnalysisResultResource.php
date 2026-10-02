<?php

namespace App\Http\Resources\Api;

use App\Models\ResultadoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ResultadoSolicitud */
class AnalysisResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'solicitud_id' => $this->resource->solicitud_id,
            'conclusion_general' => $this->resource->conclusion_general,
            'total_creditos_reconocidos' => $this->resource->total_creditos_reconocidos,
            'coordinador' => $this->whenLoaded('coordinador', fn () => [
                'id' => $this->resource->coordinador->id,
                'nombres_completos' => $this->resource->coordinador->nombres_completos,
            ]),
            'informe_tecnico_disponible' => $this->resource->ruta_informe_tecnico !== null,
            'informe_generado_at' => $this->resource->informe_generado_at,
            'created_at' => $this->resource->created_at,
        ];
    }
}
