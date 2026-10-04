<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['estudiante_id', 'universidad_origen', 'carrera_origen', 'tipo_institucion', 'periodo_cursado', 'procedencia', 'carrera_origen_id'])]
class AntecedenteAcademico extends Model
{
    public const UEB_NAME = 'Universidad Estatal de Bolívar';

    /** Acepta ciclos académicos y conserva compatibilidad con períodos históricos. */
    public const PERIOD_PATTERN = '/\A(?:(?:Primer|Segundo|Tercer|Cuarto|Quinto|Sexto|Séptimo|Octavo|Noveno|Décimo) ciclo \/ semestre|[0-9]{4}(?:-[0-9]{4})?)\z/';

    protected $table = 'antecedentes_academicos';

    /** @return BelongsTo<User, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
