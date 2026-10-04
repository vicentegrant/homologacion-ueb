<?php

namespace App\Http\Resources\Api;

use App\Models\Carrera;
use App\Models\DocumentoRequeridoProceso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Model */
class CatalogEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $data = ['id' => (int) $r->id, 'nombre' => (string) ($r instanceof DocumentoRequeridoProceso ? $r->nombre_documento : $r->nombre), 'activa' => (bool) ($r instanceof DocumentoRequeridoProceso ? $r->activo : $r->activa)];
        if ($r instanceof Carrera) {
            $data['facultad_id'] = $r->facultad_id === null ? null : (int) $r->facultad_id;
            $data['facultad'] = $r->facultad === null ? null : (string) $r->facultad->nombre;
            $data['modalidades'] = ModalidadResource::collection($r->modalidades);
        }
        if ($r instanceof DocumentoRequeridoProceso) {
            $data['tramite_proceso_id'] = (int) $r->tramite_proceso_id;
            $data['carrera_id'] = $r->carrera_id === null ? null : (int) $r->carrera_id;
            $data['descripcion'] = $r->descripcion === null ? null : (string) $r->descripcion;
            $data['obligatorio'] = (bool) $r->obligatorio;
        }

        return $data;
    }
}
