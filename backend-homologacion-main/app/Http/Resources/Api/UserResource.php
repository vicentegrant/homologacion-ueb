<?php

namespace App\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
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
            'roles' => $this->whenLoaded('roles', fn () => $this->resource->roles->pluck('nombre')->values()),
            'creador' => $this->whenLoaded('creador', fn () => $this->resource->creador === null ? null : [
                'id' => $this->resource->creador->getKey(),
                'nombres_completos' => $this->resource->creador->nombres_completos,
            ]),
            'carreras' => CareerResource::collection($this->whenLoaded('carrerasCoordinadas')),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
