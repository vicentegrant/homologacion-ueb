<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\ChangeSolicitudStateRequest;
use App\Http\Resources\Api\SolicitudResource;
use App\Services\CoordinatorAccessService;
use App\Services\SolicitudWorkflowService;
use Illuminate\Http\JsonResponse;

class SolicitudStateController extends Controller
{
    public function store(ChangeSolicitudStateRequest $request, int $solicitud, CoordinatorAccessService $access, SolicitudWorkflowService $workflow): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $updated = $workflow->transition($record, $request->string('estado')->toString(), $request->user(), $request->validated('observacion'));
        $updated->load(['carrera', 'estudiante', 'tramiteProceso.tipoTramite', 'tramiteProceso.tipoProceso', 'ultimoHistorialEstado.estadoSolicitud']);

        return response()->json(['success' => true, 'message' => 'Estado actualizado correctamente.', 'data' => SolicitudResource::make($updated)]);
    }
}
