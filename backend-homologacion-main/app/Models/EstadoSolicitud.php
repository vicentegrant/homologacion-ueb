<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre'])]
class EstadoSolicitud extends Model
{
    protected $table = 'estados_solicitud';

    /** @return HasMany<HistorialEstadoSolicitud, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEstadoSolicitud::class);
    }
}
