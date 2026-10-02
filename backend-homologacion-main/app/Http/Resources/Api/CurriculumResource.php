<?php

namespace App\Http\Resources\Api;

use App\Models\MallaCurricular;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MallaCurricular */
class CurriculumResource extends JsonResource
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
            'nombre' => $this->resource->nombre,
            'tipo' => $this->resource->tipo,
            'activa' => $this->resource->activa,
            'carrera' => CareerResource::make($this->whenLoaded('carrera')),
            'estudiante' => $this->whenLoaded('estudiante', fn () => $this->resource->estudiante === null ? null : [
                'id' => $this->resource->estudiante->id,
                'nombres_completos' => $this->resource->estudiante->nombres_completos,
            ]),
            'creador' => $this->whenLoaded('creador', fn () => [
                'id' => $this->resource->creador->id,
                'nombres_completos' => $this->resource->creador->nombres_completos,
            ]),
            'asignaturas' => SubjectResource::collection($this->whenLoaded('asignaturas')),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
