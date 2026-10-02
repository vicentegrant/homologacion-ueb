<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\StoreComparisonRequest;
use App\Http\Requests\Api\Coordinator\UpdateComparisonRequest;
use App\Http\Resources\Api\ComparisonResource;
use App\Services\AcademicAnalysisService;
use App\Services\CoordinatorAccessService;
use App\Services\SolicitudWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComparisonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, int $solicitud, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $comparisons = $record->comparacionesAsignaturas()->with(['asignaturaOrigen.mallaCurricular', 'asignaturaDestino.mallaCurricular'])->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => ComparisonResource::collection($comparisons)]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreComparisonRequest $request, int $solicitud, CoordinatorAccessService $access, AcademicAnalysisService $analysis): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $comparison = $analysis->createComparison($request->user(), $record, $request->validated());

        return response()->json(['success' => true, 'message' => 'Comparación registrada correctamente.', 'data' => ComparisonResource::make($comparison)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function update(UpdateComparisonRequest $request, int $comparacion, AcademicAnalysisService $analysis): JsonResponse
    {
        $comparison = $analysis->updateComparison($request->user(), $comparacion, $request->validated());

        return response()->json(['success' => true, 'message' => 'Comparación actualizada correctamente.', 'data' => ComparisonResource::make($comparison)]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $comparacion, CoordinatorAccessService $access, SolicitudWorkflowService $workflow): JsonResponse
    {
        DB::transaction(function () use ($request, $comparacion, $access, $workflow): void {
            $comparison = $access->comparison($request->user(), $comparacion);
            $solicitud = $access->solicitud($request->user(), $comparison->solicitud_id, true);
            abort_unless($workflow->currentState($solicitud) === 'en_proceso', 409, 'La comparación ya no puede eliminarse.');
            $comparison->delete();
        });

        return response()->json(['success' => true, 'message' => 'Comparación eliminada correctamente.']);
    }
}
