<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['estudiante_id', 'coordinador_id', 'carrera_id', 'tramite_proceso_id', 'procedencia_estudios'])]
class Solicitud extends Model
{
    protected $table = 'solicitudes';

    /** @return BelongsTo<Carrera, $this> */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /** @return BelongsTo<User, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** @return BelongsTo<User, $this> */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinador_id');
    }

    /** @return BelongsTo<TramiteProceso, $this> */
    public function tramiteProceso(): BelongsTo
    {
        return $this->belongsTo(TramiteProceso::class);
    }

    /** @return HasMany<HistorialEstadoSolicitud, $this> */
    public function historialEstados(): HasMany
    {
        return $this->hasMany(HistorialEstadoSolicitud::class);
    }

    /** @return HasOne<HistorialEstadoSolicitud, $this> */
    public function ultimoHistorialEstado(): HasOne
    {
        return $this->hasOne(HistorialEstadoSolicitud::class)->latestOfMany();
    }

    /** @return HasMany<OficioSolicitud, $this> */
    public function oficios(): HasMany
    {
        return $this->hasMany(OficioSolicitud::class);
    }

    /** @return HasMany<SolicitudDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(SolicitudDocumento::class);
    }

    /** @return HasMany<ComparacionAsignatura, $this> */
    public function comparacionesAsignaturas(): HasMany
    {
        return $this->hasMany(ComparacionAsignatura::class);
    }

    /** @return HasOne<ResultadoSolicitud, $this> */
    public function resultado(): HasOne
    {
        return $this->hasOne(ResultadoSolicitud::class);
    }

    /** @return HasOne<ResolucionSolicitud, $this> */
    public function resolucion(): HasOne
    {
        return $this->hasOne(ResolucionSolicitud::class);
    }
}
