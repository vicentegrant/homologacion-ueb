<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['solicitud_documento_id', 'observacion'])]
class ObservacionDocumentacion extends Model
{
    protected $table = 'observaciones_documentacion';

    /** @return BelongsTo<SolicitudDocumento, $this> */
    public function solicitudDocumento(): BelongsTo
    {
        return $this->belongsTo(SolicitudDocumento::class);
    }
}
