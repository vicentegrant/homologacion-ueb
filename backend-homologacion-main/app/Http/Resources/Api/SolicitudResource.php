<?php

namespace App\Http\Resources\Api;

use App\Models\HistorialEstadoSolicitud;
use App\Models\OficioSolicitud;
use App\Models\Solicitud;
use App\Models\SolicitudDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Solicitud */
class SolicitudResource extends JsonResource
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
            'carrera' => CareerResource::make($this->whenLoaded('carrera')),
            'procedencia_estudios' => $this->resource->procedencia_estudios,
            'estudiante' => $this->whenLoaded('estudiante', fn () => [
                'id' => $this->resource->estudiante->getKey(),
                'nombres_completos' => $this->resource->estudiante->nombres_completos,
                'cedula' => $this->resource->estudiante->cedula,
                'email' => $this->resource->estudiante->email,
            ]),
            'coordinador' => $this->whenLoaded('coordinador', fn () => $this->resource->coordinador === null ? null : [
                'id' => $this->resource->coordinador->getKey(),
                'nombres_completos' => $this->resource->coordinador->nombres_completos,
                'email' => $this->resource->coordinador->email,
            ]),
            'tramite' => $this->whenLoaded('tramiteProceso', fn () => [
                'id' => $this->resource->tramiteProceso->getKey(),
                'tipo_tramite' => $this->resource->tramiteProceso->tipoTramite,
                'tipo_proceso' => $this->resource->tramiteProceso->tipoProceso,
            ]),
            'estado_actual' => $this->whenLoaded('ultimoHistorialEstado', fn () => $this->resource->ultimoHistorialEstado?->estadoSolicitud),
            'documentos' => $this->whenLoaded('documentos', fn () => $this->resource->documentos->map(fn (SolicitudDocumento $document): array => [
                'id' => $document->getKey(),
                'documento_requerido' => $document->documentoRequerido,
                'estado' => $document->estadoDocumento,
                'validez' => $document->validez,
                'presentado' => $document->ruta_documento_oficio !== null,
                'download_url' => $request->user()?->hasRole('coordinador') && $document->ruta_documento_oficio !== null
                    ? route('coordinator.documents.download', $document->id, false)
                    : null,
                'observaciones' => $document->observaciones,
                'verificaciones' => $document->verificaciones,
            ])),
            'historial_estados' => $this->whenLoaded('historialEstados', fn () => $this->resource->historialEstados->map(fn (HistorialEstadoSolicitud $history): array => [
                'id' => $history->getKey(),
                'estado' => $history->estadoSolicitud,
                'etapa_origen' => $history->etapa_origen,
                'observacion' => $history->observacion,
                'usuario_responsable' => $history->relationLoaded('usuarioResponsable') && $history->usuarioResponsable !== null ? [
                    'id' => $history->usuarioResponsable->id,
                    'nombres_completos' => $history->usuarioResponsable->nombres_completos,
                ] : null,
                'created_at' => $history->created_at,
            ])),
            'oficios' => $this->whenLoaded('oficios', fn () => $this->resource->oficios->map(fn (OficioSolicitud $oficio): array => [
                'id' => $oficio->getKey(),
                'numero_oficio' => $oficio->numero_oficio,
                'fecha_oficio' => $oficio->fecha_oficio?->toDateString(),
            ])),
            'comparaciones' => ComparisonResource::collection($this->whenLoaded('comparacionesAsignaturas')),
            'resultado' => AnalysisResultResource::make($this->whenLoaded('resultado')),
            'resolucion' => ResolucionResource::make($this->whenLoaded('resolucion')),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
