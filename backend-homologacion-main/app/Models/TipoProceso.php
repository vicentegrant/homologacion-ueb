<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre'])]
class TipoProceso extends Model
{
    protected $table = 'tipos_proceso';

    /** @return HasMany<TramiteProceso, $this> */
    public function tramitesProcesos(): HasMany
    {
        return $this->hasMany(TramiteProceso::class);
    }
}
