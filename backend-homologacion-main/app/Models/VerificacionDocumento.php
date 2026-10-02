<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['solicitud_documento_id', 'coordinador_id', 'estado'])]
class VerificacionDocumento extends Model
{
    protected $table = 'verificaciones_documento';

    /** @return BelongsTo<SolicitudDocumento, $this> */
    public function solicitudDocumento(): BelongsTo
    {
        return $this->belongsTo(SolicitudDocumento::class);
    }

    /** @return BelongsTo<User, $this> */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinador_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
