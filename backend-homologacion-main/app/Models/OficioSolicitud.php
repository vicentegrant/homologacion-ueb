<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['solicitud_id', 'numero_oficio', 'fecha_oficio', 'ruta_oficio'])]
class OficioSolicitud extends Model
{
    protected $table = 'oficios_solicitud';

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_oficio' => 'date'];
    }
}
