<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['solicitud_id', 'estado_solicitud_id', 'usuario_responsable_id', 'etapa_origen', 'observacion'])]
class HistorialEstadoSolicitud extends Model
{
    protected $table = 'historial_estados_solicitud';

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<EstadoSolicitud, $this> */
    public function estadoSolicitud(): BelongsTo
    {
        return $this->belongsTo(EstadoSolicitud::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuarioResponsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_responsable_id');
    }
}
