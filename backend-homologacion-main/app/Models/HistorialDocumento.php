<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['solicitud_documento_id', 'usuario_id', 'estado', 'observacion'])]
class HistorialDocumento extends Model
{
    protected $table = 'historial_documentos';
}
