<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['estudiante_id', 'universidad_origen', 'carrera_origen', 'tipo_institucion', 'periodo_cursado'])]
class AntecedenteAcademico extends Model
{
    protected $table = 'antecedentes_academicos';

    /** @return BelongsTo<User, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
