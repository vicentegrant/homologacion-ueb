<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\StoreResolutionRequest;
use App\Http\Resources\Api\ResolucionResource;
use App\Services\CoordinatorAccessService;
use App\Services\ResolutionService;
use App\Services\SolicitudWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResolutionController extends Controller
{
    public function store(
        StoreResolutionRequest $request,
        int $solicitud,
        CoordinatorAccessService $access,
        ResolutionService $resolutions,
        SolicitudWorkflowService $workflow,
    ): JsonResponse {
        $record = $access->solicitud($request->user(), $solicitud);
        abort_unless($workflow->currentState($record) === 'en_consejo', 409, 'La solicitud no está pendiente de resolución.');
        abort_if($record->resolucion()->exists(), 409, 'La solicitud ya tiene una resolución registrada.');

        $resolution = $resolutions->create(
            $record,
            $request->user(),
            $request->validated(),
        )->load('coordinador');

        return response()->json(['success' => true, 'message' => 'Resolución registrada correctamente.', 'data' => ResolucionResource::make($resolution)], 201);
    }

    public function download(Request $request, int $solicitud, CoordinatorAccessService $access): StreamedResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $resolution = $record->resolucion;
        abort_unless($resolution && Storage::disk('local')->exists($resolution->ruta_archivo), 404);

        return Storage::disk('local')->download($resolution->ruta_archivo, $resolution->numero_resolucion.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
