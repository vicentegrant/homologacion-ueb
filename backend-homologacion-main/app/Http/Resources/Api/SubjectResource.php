<?php

namespace App\Http\Resources\Api;

use App\Models\AsignaturaCredito;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AsignaturaCredito */
class SubjectResource extends JsonResource
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
            'malla_curricular_id' => $this->resource->malla_curricular_id,
            'codigo_asignatura' => $this->resource->codigo_asignatura,
            'nombre_asignatura' => $this->resource->nombre_asignatura,
            'numero_creditos' => $this->resource->numero_creditos,
            'nivel_ciclo' => $this->resource->nivel_ciclo,
            'hr_carga_horaria' => $this->resource->hr_carga_horaria,
            'malla' => CurriculumResource::make($this->whenLoaded('mallaCurricular')),
            'temas_silabo' => $this->whenLoaded('temasSilabo'),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
