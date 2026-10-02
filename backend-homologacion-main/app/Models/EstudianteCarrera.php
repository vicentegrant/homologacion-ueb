<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['estudiante_id', 'coordinador_carrera_id'])]
class EstudianteCarrera extends Model
{
    /** @return BelongsTo<User, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** @return BelongsTo<CoordinadorCarrera, $this> */
    public function coordinadorCarrera(): BelongsTo
    {
        return $this->belongsTo(CoordinadorCarrera::class);
    }
}
