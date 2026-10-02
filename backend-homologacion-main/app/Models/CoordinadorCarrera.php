<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['coordinador_id', 'carrera_id'])]
class CoordinadorCarrera extends Model
{
    /** @return BelongsTo<User, $this> */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinador_id');
    }

    /** @return BelongsTo<Carrera, $this> */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /** @return HasMany<EstudianteCarrera, $this> */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(EstudianteCarrera::class);
    }
}
