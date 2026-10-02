<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\SolicitudFilterRequest;
use App\Http\Resources\Api\SolicitudResource;
use App\Models\Solicitud;
use App\Services\SolicitudQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SolicitudController extends Controller
{
    public function index(SolicitudFilterRequest $request, SolicitudQueryService $queryService): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $solicitudes = $queryService->build($filters)
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return SolicitudResource::collection($solicitudes)->additional(['success' => true]);
    }

    public function show(Solicitud $solicitud): JsonResponse
    {
        $solicitud->load([
            'carrera',
            'estudiante.roles',
            'estudiante.antecedentesAcademicos',
            'coordinador.roles',
            'tramiteProceso.tipoTramite',
            'tramiteProceso.tipoProceso',
            'documentos.documentoRequerido.carrera',
            'documentos.estadoDocumento',
            'documentos.observaciones',
            'documentos.verificaciones.coordinador',
            'historialEstados.estadoSolicitud',
            'ultimoHistorialEstado.estadoSolicitud',
            'oficios',
            'resultado.coordinador',
            'resolucion.coordinador',
        ]);

        return response()->json(['success' => true, 'data' => SolicitudResource::make($solicitud)]);
    }
}
