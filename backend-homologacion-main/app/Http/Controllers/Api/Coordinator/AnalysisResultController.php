<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\StoreAnalysisResultRequest;
use App\Http\Resources\Api\AnalysisResultResource;
use App\Services\AcademicAnalysisService;
use App\Services\CoordinatorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisResultController extends Controller
{
    public function show(Request $request, int $solicitud, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $result = $record->resultado()->with('coordinador')->firstOrFail();

        return response()->json(['success' => true, 'data' => AnalysisResultResource::make($result)]);
    }

    public function store(StoreAnalysisResultRequest $request, int $solicitud, CoordinatorAccessService $access, AcademicAnalysisService $analysis): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $result = $analysis->createResult($request->user(), $record, $request->validated())->load('coordinador');

        return response()->json(['success' => true, 'message' => 'Resultado académico registrado correctamente.', 'data' => AnalysisResultResource::make($result)], 201);
    }
}
