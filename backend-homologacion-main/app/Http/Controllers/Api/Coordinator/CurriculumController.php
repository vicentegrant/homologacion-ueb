<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\CurriculumIndexRequest;
use App\Http\Requests\Api\Coordinator\StoreCurriculumRequest;
use App\Http\Requests\Api\Coordinator\UpdateCurriculumRequest;
use App\Http\Requests\Api\Coordinator\UpdateCurriculumStatusRequest;
use App\Http\Resources\Api\CurriculumResource;
use App\Models\MallaCurricular;
use App\Services\CoordinatorAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurriculumController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CurriculumIndexRequest $request, CoordinatorAccessService $access): AnonymousResourceCollection
    {
        $filters = $request->validated();
        if (isset($filters['carrera'])) {
            $access->ensureCareer($request->user(), (int) $filters['carrera']);
        }
        $records = $access->curricula($request->user())
            ->with(['carrera', 'estudiante', 'creador'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('nombre', 'like', "%{$search}%"))
            ->when($filters['tipo'] ?? null, fn (Builder $query, string $type) => $query->where('tipo', $type))
            ->when($filters['carrera'] ?? null, fn (Builder $query, int $career) => $query->where('carrera_id', $career))
            ->when($filters['estudiante'] ?? null, fn (Builder $query, int $student) => $query->where('estudiante_id', $student))
            ->when(array_key_exists('activa', $filters), fn (Builder $query) => $query->where('activa', $filters['activa']))
            ->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return CurriculumResource::collection($records)->additional(['success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCurriculumRequest $request, CoordinatorAccessService $access): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['carrera_id'])) {
            $access->ensureCareer($request->user(), (int) $data['carrera_id']);
        }
        if ($data['tipo'] === 'origen') {
            $access->student($request->user(), (int) $data['estudiante_id']);
        } else {
            $data['estudiante_id'] = null;
        }
        $record = MallaCurricular::query()->create([
            ...$data,
            'creador_id' => $request->user()->id,
        ])->load(['carrera', 'estudiante', 'creador']);

        return response()->json(['success' => true, 'message' => 'Malla curricular creada correctamente.', 'data' => CurriculumResource::make($record)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $malla, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->curriculum($request->user(), $malla)->load(['carrera', 'estudiante', 'creador', 'asignaturas.temasSilabo']);

        return response()->json(['success' => true, 'data' => CurriculumResource::make($record)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCurriculumRequest $request, int $malla, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->curriculum($request->user(), $malla);
        $record->update($request->validated());

        return response()->json(['success' => true, 'message' => 'Malla curricular actualizada correctamente.', 'data' => CurriculumResource::make($record->load(['carrera', 'estudiante', 'creador']))]);
    }

    public function updateStatus(UpdateCurriculumStatusRequest $request, int $malla, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->curriculum($request->user(), $malla);
        $record->update(['activa' => $request->boolean('activa')]);

        return response()->json(['success' => true, 'message' => 'Estado de la malla actualizado correctamente.', 'data' => CurriculumResource::make($record->load(['carrera', 'estudiante', 'creador']))]);
    }
}
