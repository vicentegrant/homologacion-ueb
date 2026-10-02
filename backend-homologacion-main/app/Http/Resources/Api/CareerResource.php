<?php

namespace App\Http\Resources\Api;

use App\Models\Carrera;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Carrera */
class CareerResource extends JsonResource
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
            'nombre' => $this->resource->nombre,
        ];
    }
}
