<?php

namespace App\Http\Resources\Api;

use App\Models\ObservacionDocumentacion;
use App\Models\SolicitudDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SolicitudDocumento */
class StudentDocumentResource extends JsonResource
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
            'requisito' => $this->whenLoaded('documentoRequerido', fn (): array => [
                'id' => $this->resource->documentoRequerido->id,
                'nombre' => $this->resource->documentoRequerido->nombre_documento,
                'descripcion' => $this->resource->documentoRequerido->descripcion,
            ]),
            'estado' => $this->whenLoaded('estadoDocumento', fn (): string => $this->resource->estadoDocumento->nombre),
            'validez' => $this->resource->validez,
            'presentado' => $this->resource->ruta_documento_oficio !== null,
            'download_url' => $this->resource->ruta_documento_oficio === null ? null : route('student.documentos.download', [
                'solicitud' => $this->resource->solicitud_id, 'documento' => $this->resource->id,
            ], false),
            'observaciones' => $this->whenLoaded('observaciones', fn () => $this->resource->observaciones->map(fn (ObservacionDocumentacion $observation): array => [
                'id' => $observation->id, 'observacion' => $observation->observacion, 'created_at' => $observation->created_at,
            ])),
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
