<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['solicitud_id', 'coordinador_id', 'numero_resolucion', 'fecha_aprobacion', 'ruta_archivo'])]
class ResolucionSolicitud extends Model
{
    protected $table = 'resoluciones_solicitud';

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<User, $this> */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinador_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_aprobacion' => 'date'];
    }
}
