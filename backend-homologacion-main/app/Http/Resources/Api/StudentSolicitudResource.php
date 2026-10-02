<?php

namespace App\Http\Resources\Api;

use App\Models\HistorialEstadoSolicitud;
use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Solicitud */
class StudentSolicitudResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->resource->ultimoHistorialEstado?->estadoSolicitud?->nombre;

        return [
            'id' => $this->resource->id,
            'procedencia_estudios' => $this->resource->procedencia_estudios,
            'carrera' => CareerResource::make($this->whenLoaded('carrera')),
            'coordinador' => $this->whenLoaded('coordinador', fn () => $this->resource->coordinador === null ? null : [
                'id' => $this->resource->coordinador->id, 'nombres_completos' => $this->resource->coordinador->nombres_completos,
            ]),
            'tramite' => $this->whenLoaded('tramiteProceso', fn (): array => [
                'id' => $this->resource->tramiteProceso->id,
                'tipo_tramite' => $this->resource->tramiteProceso->tipoTramite->nombre,
                'tipo_proceso' => $this->resource->tramiteProceso->tipoProceso->nombre,
            ]),
            'estado_actual' => $state,
            'puede_editar' => $state === 'pendiente',
            'puede_enviar' => in_array($state, ['pendiente', 'observado'], true),
            'documentos' => StudentDocumentResource::collection($this->whenLoaded('documentos')),
            'historial_estados' => $this->whenLoaded('historialEstados', fn () => $this->resource->historialEstados->sortBy('id')->values()->map(fn (HistorialEstadoSolicitud $history): array => [
                'id' => $history->id, 'estado' => $history->estadoSolicitud->nombre,
                'observacion' => $history->observacion, 'created_at' => $history->created_at,
            ])),
            'resultado' => $this->whenLoaded('resultado', fn () => $this->resource->resultado === null ? null : [
                'conclusion_general' => $this->resource->resultado->conclusion_general,
                'total_creditos_reconocidos' => $this->resource->resultado->total_creditos_reconocidos,
            ]),
            'resolucion' => $this->whenLoaded('resolucion', fn () => $state !== 'listo' || $this->resource->resolucion === null ? null : [
                'numero_resolucion' => $this->resource->resolucion->numero_resolucion,
                'fecha_aprobacion' => $this->resource->resolucion->fecha_aprobacion?->toDateString(),
                'download_url' => route('student.resolucion.download', $this->resource->id, false),
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
