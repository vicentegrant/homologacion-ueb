<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\StudentIndexRequest;
use App\Http\Resources\Api\StudentResource;
use App\Services\CoordinatorAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentController extends Controller
{
    public function index(StudentIndexRequest $request, CoordinatorAccessService $access): AnonymousResourceCollection
    {
        $filters = $request->validated();
        if (isset($filters['carrera'])) {
            $access->ensureCareer($request->user(), (int) $filters['carrera']);
        }

        $students = $access->students($request->user())
            ->with('antecedentesAcademicos')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('nombres_completos', 'like', "%{$search}%")
                    ->orWhere('cedula', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->when(array_key_exists('cuenta_activa', $filters), fn (Builder $query) => $query->where('cuenta_activa', $filters['cuenta_activa']))
            ->when($filters['carrera'] ?? null, fn (Builder $query, int $careerId) => $query
                ->whereHas('carrerasComoEstudiante.coordinadorCarrera', fn (Builder $assignment) => $assignment->where('carrera_id', $careerId)))
            ->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return StudentResource::collection($students)->additional(['success' => true]);
    }

    public function show(Request $request, int $student, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->student($request->user(), $student);
        $careerIds = $access->careerIds($request->user());
        $record->load([
            'antecedentesAcademicos',
            'carrerasComoEstudiante' => fn ($query) => $query->whereHas('coordinadorCarrera', fn ($assignment) => $assignment->whereIn('carrera_id', $careerIds)),
            'carrerasComoEstudiante.coordinadorCarrera.carrera',
            'carrerasComoEstudiante.coordinadorCarrera.coordinador',
            'solicitudesComoEstudiante' => fn ($query) => $query->whereIn('carrera_id', $careerIds),
            'solicitudesComoEstudiante.carrera', 'solicitudesComoEstudiante.tramiteProceso.tipoTramite',
            'solicitudesComoEstudiante.tramiteProceso.tipoProceso', 'solicitudesComoEstudiante.ultimoHistorialEstado.estadoSolicitud',
        ]);

        return response()->json(['success' => true, 'data' => StudentResource::make($record)]);
    }
}
