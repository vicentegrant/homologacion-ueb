<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\SaveRequirementRequest;
use App\Http\Resources\Api\CatalogEntryResource;
use App\Models\Carrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\TramiteProceso;
use App\Models\User;
use App\Services\CoordinatorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequirementController extends Controller
{
    public function __construct(private CoordinatorAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $careerIds = $this->access->careerIds($request->user());

        return response()->json(['success' => true, 'data' => [
            'carreras' => Carrera::query()->whereIn('id', $careerIds)->orderBy('nombre')->get(['id', 'nombre', 'activa']),
            'tramites' => TramiteProceso::query()->with(['tipoTramite', 'tipoProceso'])->orderBy('id')->get()
                ->map(fn (TramiteProceso $process): array => ['id' => $process->id, 'tipo_tramite' => $process->tipoTramite->nombre, 'tipo_proceso' => $process->tipoProceso->nombre]),
            'requisitos' => CatalogEntryResource::collection(DocumentoRequeridoProceso::query()->whereIn('carrera_id', $careerIds)->orderBy('nombre_documento')->get()),
            'generales' => CatalogEntryResource::collection(DocumentoRequeridoProceso::query()->whereNull('carrera_id')->where('activo', true)->orderBy('nombre_documento')->get()),
        ]]);
    }

    public function store(SaveRequirementRequest $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new CatalogEntryResource($this->save($request))], 201);
    }

    public function update(SaveRequirementRequest $request, int $requirement): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new CatalogEntryResource($this->save($request, $requirement))]);
    }

    private function save(SaveRequirementRequest $request, ?int $id = null): DocumentoRequeridoProceso
    {
        $data = $request->validated();
        $this->access->ensureCareer($request->user(), $data['carrera_id']);

        return DB::transaction(function () use ($request, $data, $id): DocumentoRequeridoProceso {
            Carrera::query()->whereKey($data['carrera_id'])->lockForUpdate()->firstOrFail();
            $record = $id ? $this->owned($request->user(), $id, true) : new DocumentoRequeridoProceso;
            if ($id && $record->documentosPresentados()->exists()) {
                abort_if($record->carrera_id !== (int) $data['carrera_id'] || $record->tramite_proceso_id !== (int) $data['tramite_proceso_id'], 409, 'Un requisito utilizado no puede trasladarse a otra carrera o trámite.');
            }
            $name = trim($data['nombre']);
            $duplicates = DocumentoRequeridoProceso::query()->where('tramite_proceso_id', $data['tramite_proceso_id'])
                ->where(fn ($query) => $query->where('carrera_id', $data['carrera_id'])
                    ->when($data['activa'], fn ($query) => $query->orWhere(fn ($global) => $global->whereNull('carrera_id')->where('activo', true))))
                ->when($id, fn ($query) => $query->whereKeyNot($id))->get();
            if ($duplicates->contains(fn (DocumentoRequeridoProceso $existing): bool => Str::lower(trim($existing->nombre_documento)) === Str::lower($name))) {
                throw ValidationException::withMessages(['nombre' => 'Este documento ya está configurado para el trámite. Edite el existente o revise los requisitos institucionales.']);
            }
            // Los expedientes guardan su propia copia; esta configuración afecta nuevas solicitudes.
            $record->fill([
                'nombre_documento' => $name, 'descripcion' => $data['descripcion'] ?? null,
                'carrera_id' => $data['carrera_id'], 'tramite_proceso_id' => $data['tramite_proceso_id'],
                'activo' => $data['activa'], 'obligatorio' => $data['obligatorio'],
            ])->save();

            return $record->refresh();
        });
    }

    private function owned(User $coordinator, int $id, bool $lock = false): DocumentoRequeridoProceso
    {
        return DocumentoRequeridoProceso::query()->whereIn('carrera_id', $this->access->careerIds($coordinator))
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }

    public function destroy(Request $request, int $requirement): JsonResponse
    {
        DB::transaction(function () use ($request, $requirement): void {
            $record = $this->owned($request->user(), $requirement, true);
            abort_if($record->documentosPresentados()->exists(), 409, 'El requisito ya forma parte de un expediente. Desactívelo para nuevas solicitudes; su historial se conserva.');
            $record->delete();
        });

        return response()->json(['success' => true, 'message' => 'Requisito eliminado.']);
    }
}
