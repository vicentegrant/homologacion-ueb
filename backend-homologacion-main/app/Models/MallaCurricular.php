<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo', 'carrera_id', 'creador_id', 'estudiante_id', 'activa'])]
class MallaCurricular extends Model
{
    protected $table = 'mallas_curriculares';

    /** @return BelongsTo<Carrera, $this> */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creador_id');
    }

    /** @return BelongsTo<User, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** @return HasMany<AsignaturaCredito, $this> */
    public function asignaturas(): HasMany
    {
        return $this->hasMany(AsignaturaCredito::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
