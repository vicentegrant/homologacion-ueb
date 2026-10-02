<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['malla_curricular_id', 'codigo_asignatura', 'nombre_asignatura', 'numero_creditos', 'nivel_ciclo', 'hr_carga_horaria'])]
class AsignaturaCredito extends Model
{
    protected $table = 'asignaturas_creditos';

    /** @return BelongsTo<MallaCurricular, $this> */
    public function mallaCurricular(): BelongsTo
    {
        return $this->belongsTo(MallaCurricular::class);
    }

    /** @return HasMany<TemaSilaboAsignatura, $this> */
    public function temasSilabo(): HasMany
    {
        return $this->hasMany(TemaSilaboAsignatura::class, 'asignatura_id');
    }

    /** @return HasMany<ComparacionAsignatura, $this> */
    public function comparacionesComoOrigen(): HasMany
    {
        return $this->hasMany(ComparacionAsignatura::class, 'asignatura_origen_id');
    }

    /** @return HasMany<ComparacionAsignatura, $this> */
    public function comparacionesComoDestino(): HasMany
    {
        return $this->hasMany(ComparacionAsignatura::class, 'asignatura_destino_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hr_carga_horaria' => 'integer',
            'numero_creditos' => 'integer',
        ];
    }
}
