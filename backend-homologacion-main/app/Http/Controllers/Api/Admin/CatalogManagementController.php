<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\SaveCatalogRequest;
use App\Http\Resources\Api\CatalogEntryResource;
use App\Models\Carrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\TramiteProceso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CatalogManagementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'facultades' => CatalogEntryResource::collection(Facultad::orderBy('nombre')->get()),
            'modalidades' => CatalogEntryResource::collection(Modalidad::orderBy('nombre')->get()),
            'carreras' => CatalogEntryResource::collection(Carrera::with(['facultad', 'modalidades'])->orderBy('nombre')->get()),
            'requisitos' => CatalogEntryResource::collection(DocumentoRequeridoProceso::orderBy('nombre_documento')->get()),
            'tramites' => TramiteProceso::with(['tipoTramite', 'tipoProceso'])->get()->map(fn ($p): array => ['id' => $p->id, 'nombre' => $p->tipoTramite->nombre.' · '.$p->tipoProceso->nombre]),
        ]]);
    }

    public function show(string $catalog, int $entry): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new CatalogEntryResource($this->model($catalog)->findOrFail($entry))]);
    }

    public function store(SaveCatalogRequest $request, string $catalog): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new CatalogEntryResource($this->save($request, $catalog))], 201);
    }

    public function update(SaveCatalogRequest $request, string $catalog, int $entry): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new CatalogEntryResource($this->save($request, $catalog, $entry))]);
    }

    private function save(SaveCatalogRequest $request, string $catalog, ?int $entry = null): Model
    {
        $record = DB::transaction(function () use ($request, $catalog, $entry): Model {
            $model = $this->model($catalog);
            $record = $entry ? $model->lockForUpdate()->findOrFail($entry) : $model;
            $data = $request->validated();
            $modalities = $data['modalidad_ids'] ?? [];
            unset($data['modalidad_ids']);
            if ($catalog === 'requisitos') {
                $data['nombre_documento'] = $data['nombre'];
                $data['activo'] = $data['activa'];
                unset($data['nombre'],$data['activa']);
            }
            if ($catalog === 'carreras' && $entry) {
                $used = DB::table('estudiante_carreras')->join('coordinador_carreras', 'coordinador_carreras.id', '=', 'estudiante_carreras.coordinador_carrera_id')->where('carrera_id', $entry)->whereNotNull('modalidad_id')->pluck('modalidad_id')->all();
                abort_if(count(array_diff($used, $modalities)) > 0, 409, 'No puede retirar modalidades que tienen estudiantes asignados.');
            }
            $record->fill($data)->save();
            if ($catalog === 'carreras') {
                $record->modalidades()->sync($modalities);
            }

            return $record->refresh();
        });

        return $record;
    }

    public function destroy(string $catalog, int $entry): JsonResponse
    {
        try {
            DB::transaction(function () use ($catalog, $entry): void {
                $record = $this->model($catalog)->lockForUpdate()->findOrFail($entry);
                if ($catalog === 'carreras') {
                    abort_if($record->documentosRequeridos()->exists() || $record->coordinadores()->exists() || $record->mallasCurriculares()->exists() || DB::table('solicitudes')->where('carrera_id', $entry)->exists(), 409, 'La carrera está en uso. Desactívela para conservar su historial.');
                }
                if ($catalog === 'requisitos') {
                    abort_if(DB::table('solicitud_documentos')->where('documento_requerido_proceso_id', $entry)->exists(), 409, 'El requisito está en uso. Desactívelo; los expedientes conservarán su checklist.');
                }
                $record->delete();
            });
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23503', '23001'], true)) {
                abort(409, 'El registro está en uso. Puede desactivarlo para conservar las referencias.');
            }
            throw $e;
        }

        return response()->json(['success' => true, 'message' => 'Registro eliminado.']);
    }

    private function model(string $catalog): Model
    {
        return match ($catalog) {
            'facultades' => new Facultad, 'modalidades' => new Modalidad, 'carreras' => new Carrera, 'requisitos' => new DocumentoRequeridoProceso, default => abort(404)
        };
    }
}
