<?php

namespace App\Http\Resources\Api;

use App\Models\ObservacionDocumentacion;
use App\Models\SolicitudDocumento;
use App\Models\VerificacionDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SolicitudDocumento */
class CoordinatorDocumentResource extends JsonResource
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
            'requisito' => $this->whenLoaded('documentoRequerido', fn (): array => [
                'id' => $this->resource->documentoRequerido->id,
                'nombre' => $this->resource->documentoRequerido->nombre_documento,
                'descripcion' => $this->resource->documentoRequerido->descripcion,
            ]),
            'estado' => $this->whenLoaded('estadoDocumento', fn (): string => $this->resource->estadoDocumento->nombre),
            'validez' => $this->resource->validez,
            'presentado' => $this->resource->ruta_documento_oficio !== null,
            'download_url' => $this->resource->ruta_documento_oficio === null ? null : route('coordinator.documents.download', $this->resource->id, false),
            'observaciones' => $this->whenLoaded('observaciones', fn () => $this->resource->observaciones->map(fn (ObservacionDocumentacion $record): array => [
                'id' => $record->id,
                'observacion' => $record->observacion,
                'created_at' => $record->created_at,
            ])),
            'verificaciones' => $this->whenLoaded('verificaciones', fn () => $this->resource->verificaciones->map(fn (VerificacionDocumento $record): array => [
                'id' => $record->id,
                'estado' => $record->estado,
                'coordinador' => $record->relationLoaded('coordinador') ? [
                    'id' => $record->coordinador->id,
                    'nombres_completos' => $record->coordinador->nombres_completos,
                ] : null,
                'updated_at' => $record->updated_at,
            ])),
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
