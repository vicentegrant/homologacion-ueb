<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $informe_generado_at */
#[Fillable(['solicitud_id', 'coordinador_id', 'conclusion_general', 'total_creditos_reconocidos', 'ruta_informe_tecnico', 'informe_generado_at'])]
class ResultadoSolicitud extends Model
{
    protected $table = 'resultados_solicitud';

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
        return [
            'total_creditos_reconocidos' => 'integer',
            'informe_generado_at' => 'datetime',
        ];
    }
}
