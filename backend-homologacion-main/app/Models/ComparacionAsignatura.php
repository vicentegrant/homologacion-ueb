<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['solicitud_id', 'asignatura_origen_id', 'asignatura_destino_id', 'porcentaje_coincidencia', 'observacion'])]
class ComparacionAsignatura extends Model
{
    protected $table = 'comparaciones_asignatura';

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<AsignaturaCredito, $this> */
    public function asignaturaOrigen(): BelongsTo
    {
        return $this->belongsTo(AsignaturaCredito::class, 'asignatura_origen_id');
    }

    /** @return BelongsTo<AsignaturaCredito, $this> */
    public function asignaturaDestino(): BelongsTo
    {
        return $this->belongsTo(AsignaturaCredito::class, 'asignatura_destino_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['porcentaje_coincidencia' => 'decimal:2'];
    }
}
