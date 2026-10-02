<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asignatura_id', 'tema', 'unidad_analitica'])]
class TemaSilaboAsignatura extends Model
{
    protected $table = 'temas_silabo_asignaturas';

    /** @return BelongsTo<AsignaturaCredito, $this> */
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(AsignaturaCredito::class);
    }
}
