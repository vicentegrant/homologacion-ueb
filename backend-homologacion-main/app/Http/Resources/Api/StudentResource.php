<?php

namespace App\Http\Resources\Api;

use App\Models\EstudianteCarrera;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class StudentResource extends JsonResource
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
            'nombres_completos' => $this->resource->nombres_completos,
            'cedula' => $this->resource->cedula,
            'email' => $this->resource->email,
            'numero_celular' => $this->resource->numero_celular,
            'cuenta_activa' => $this->resource->cuenta_activa,
            'antecedentes_academicos' => $this->whenLoaded('antecedentesAcademicos'),
            'carreras' => $this->whenLoaded('carrerasComoEstudiante', fn () => $this->resource->carrerasComoEstudiante->map(fn (EstudianteCarrera $assignment): array => [
                'id' => $assignment->coordinadorCarrera?->carrera?->getKey(),
                'nombre' => $assignment->coordinadorCarrera?->carrera?->nombre,
                'coordinador' => $assignment->coordinadorCarrera?->coordinador === null ? null : [
                    'id' => $assignment->coordinadorCarrera->coordinador->getKey(),
                    'nombres_completos' => $assignment->coordinadorCarrera->coordinador->nombres_completos,
                ],
            ])),
            'solicitudes' => SolicitudResource::collection($this->whenLoaded('solicitudesComoEstudiante')),
            'created_at' => $this->resource->created_at,
        ];
    }
}
