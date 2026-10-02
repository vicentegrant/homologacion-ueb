<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\SolicitudFilterRequest;
use App\Http\Resources\Api\SolicitudResource;
use App\Services\CoordinatorAccessService;
use App\Services\SolicitudQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SolicitudController extends Controller
{
    public function index(SolicitudFilterRequest $request, CoordinatorAccessService $access, SolicitudQueryService $queries): AnonymousResourceCollection
    {
        $filters = $request->validated();
        if (isset($filters['carrera'])) {
            $access->ensureCareer($request->user(), (int) $filters['carrera']);
        }
        $records = $queries->build($filters, $access->solicitudes($request->user()))
            ->paginate($filters['per_page'] ?? 15)->withQueryString();

        return SolicitudResource::collection($records)->additional(['success' => true]);
    }

    public function show(Request $request, int $solicitud, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $record->load([
            'carrera', 'estudiante.roles', 'estudiante.antecedentesAcademicos', 'coordinador.roles',
            'tramiteProceso.tipoTramite', 'tramiteProceso.tipoProceso', 'documentos.documentoRequerido.carrera',
            'documentos.estadoDocumento', 'documentos.observaciones', 'documentos.verificaciones.coordinador',
            'historialEstados.estadoSolicitud', 'historialEstados.usuarioResponsable', 'ultimoHistorialEstado.estadoSolicitud',
            'oficios', 'comparacionesAsignaturas.asignaturaOrigen.mallaCurricular',
            'comparacionesAsignaturas.asignaturaDestino.mallaCurricular', 'resultado.coordinador', 'resolucion.coordinador',
        ]);

        return response()->json(['success' => true, 'data' => SolicitudResource::make($record)]);
    }
}
