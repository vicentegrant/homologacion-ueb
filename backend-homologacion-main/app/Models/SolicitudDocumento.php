<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['solicitud_id', 'documento_requerido_proceso_id', 'estado_documento_id', 'ruta_documento_oficio', 'validez', 'recibido_at', 'recibido_por_id', 'revisado_at', 'revisado_por_id', 'obligatorio', 'requisito_nombre', 'requisito_descripcion'])]
class SolicitudDocumento extends Model
{
    protected $table = 'solicitud_documentos';

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialDocumento::class);
    }

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<DocumentoRequeridoProceso, $this> */
    public function documentoRequerido(): BelongsTo
    {
        return $this->belongsTo(DocumentoRequeridoProceso::class, 'documento_requerido_proceso_id');
    }

    /** @return BelongsTo<EstadoDocumento, $this> */
    public function estadoDocumento(): BelongsTo
    {
        return $this->belongsTo(EstadoDocumento::class);
    }

    /** @return HasMany<ObservacionDocumentacion, $this> */
    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionDocumentacion::class);
    }

    /** @return HasMany<VerificacionDocumento, $this> */
    public function verificaciones(): HasMany
    {
        return $this->hasMany(VerificacionDocumento::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['validez' => 'boolean', 'obligatorio' => 'boolean', 'recibido_at' => 'datetime', 'revisado_at' => 'datetime'];
    }
}
