<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'facultad_id', 'activa'])]
class Carrera extends Model
{
    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class);
    }

    public function modalidades(): BelongsToMany
    {
        return $this->belongsToMany(Modalidad::class, 'carrera_modalidad');
    }

    /** @return HasMany<DocumentoRequeridoProceso, $this> */
    public function documentosRequeridos(): HasMany
    {
        return $this->hasMany(DocumentoRequeridoProceso::class);
    }

    /** @return HasMany<CoordinadorCarrera, $this> */
    public function coordinadores(): HasMany
    {
        return $this->hasMany(CoordinadorCarrera::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function usuariosCoordinadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coordinador_carreras', 'carrera_id', 'coordinador_id')
            ->withTimestamps();
    }

    /** @return HasMany<MallaCurricular, $this> */
    public function mallasCurriculares(): HasMany
    {
        return $this->hasMany(MallaCurricular::class);
    }
}
