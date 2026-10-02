<?php

namespace App\Http\Resources\Api;

use App\Models\AntecedenteAcademico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AntecedenteAcademico */
class AcademicBackgroundResource extends JsonResource
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
            'universidad_origen' => $this->resource->universidad_origen,
            'carrera_origen' => $this->resource->carrera_origen,
            'tipo_institucion' => $this->resource->tipo_institucion,
            'periodo_cursado' => $this->resource->periodo_cursado,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
