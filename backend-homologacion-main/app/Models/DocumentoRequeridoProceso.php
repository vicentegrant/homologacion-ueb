<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tramite_proceso_id', 'carrera_id', 'nombre_documento', 'descripcion', 'ruta_ejemplo'])]
class DocumentoRequeridoProceso extends Model
{
    protected $table = 'documentos_requeridos_proceso';

    /** @return BelongsTo<TramiteProceso, $this> */
    public function tramiteProceso(): BelongsTo
    {
        return $this->belongsTo(TramiteProceso::class);
    }

    /** @return BelongsTo<Carrera, $this> */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /** @return HasMany<SolicitudDocumento, $this> */
    public function documentosPresentados(): HasMany
    {
        return $this->hasMany(SolicitudDocumento::class);
    }
}
