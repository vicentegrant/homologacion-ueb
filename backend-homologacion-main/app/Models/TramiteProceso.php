<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tipo_tramite_id', 'tipo_proceso_id'])]
class TramiteProceso extends Model
{
    protected $table = 'tramite_proceso';

    /** @return BelongsTo<TipoTramite, $this> */
    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    /** @return BelongsTo<TipoProceso, $this> */
    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(TipoProceso::class);
    }

    /** @return HasMany<DocumentoRequeridoProceso, $this> */
    public function documentosRequeridos(): HasMany
    {
        return $this->hasMany(DocumentoRequeridoProceso::class);
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }
}
