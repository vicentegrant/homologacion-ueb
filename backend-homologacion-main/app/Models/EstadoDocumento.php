<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre'])]
class EstadoDocumento extends Model
{
    protected $table = 'estados_documento';

    /** @return HasMany<SolicitudDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(SolicitudDocumento::class);
    }
}
