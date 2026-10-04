<?php

namespace App\Http\Resources\Api;

use App\Models\Modalidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Modalidad */
class ModalidadResource extends JsonResource
{
    /** @return array{id:int,nombre:string,activa:bool} */
    public function toArray(Request $request): array
    {
        return ['id' => (int) $this->resource->id, 'nombre' => (string) $this->resource->nombre, 'activa' => (bool) $this->resource->activa];
    }
}
