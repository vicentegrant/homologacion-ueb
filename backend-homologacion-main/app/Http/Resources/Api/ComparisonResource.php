<?php

namespace App\Http\Resources\Api;

use App\Models\AsignaturaCredito;
use App\Models\ComparacionAsignatura;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ComparacionAsignatura */
class ComparisonResource extends JsonResource
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
            'asignatura_origen' => $this->whenLoaded('asignaturaOrigen', fn () => $this->subject($this->resource->asignaturaOrigen)),
            'asignatura_destino' => $this->whenLoaded('asignaturaDestino', fn () => $this->subject($this->resource->asignaturaDestino)),
            'porcentaje_coincidencia' => $this->resource->porcentaje_coincidencia,
            'observacion' => $this->resource->observacion,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    private function subject(AsignaturaCredito $subject): array
    {
        return [
            'id' => $subject->id,
            'codigo' => $subject->codigo_asignatura,
            'nombre' => $subject->nombre_asignatura,
            'creditos' => $subject->numero_creditos,
            'nivel_ciclo' => $subject->nivel_ciclo,
            'carga_horaria' => $subject->hr_carga_horaria,
            'malla' => $subject->relationLoaded('mallaCurricular') ? [
                'id' => $subject->mallaCurricular->id,
                'nombre' => $subject->mallaCurricular->nombre,
                'tipo' => $subject->mallaCurricular->tipo,
            ] : null,
        ];
    }
}
