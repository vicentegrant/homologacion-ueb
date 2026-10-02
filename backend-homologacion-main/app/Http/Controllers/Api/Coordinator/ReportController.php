<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\SolicitudFilterRequest;
use App\Http\Resources\Api\SolicitudResource;
use App\Models\EstadoSolicitud;
use App\Services\CoordinatorAccessService;
use App\Services\SolicitudQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function solicitudes(SolicitudFilterRequest $request, CoordinatorAccessService $access, SolicitudQueryService $queries): JsonResponse
    {
        $filters = $request->validated();
        if (isset($filters['carrera'])) {
            $access->ensureCareer($request->user(), (int) $filters['carrera']);
        }
        $baseQuery = $queries->build($filters, $access->solicitudes($request->user()));
        $total = (clone $baseQuery)->count();
        /** @var list<array{estado: string, total: int}> $byState */
        $byState = [];
        foreach (EstadoSolicitud::query()->orderBy('nombre')->get() as $state) {
            $count = (clone $baseQuery)
                ->whereHas('ultimoHistorialEstado', fn (Builder $query) => $query->where('estado_solicitud_id', $state->id))
                ->count();
            $byState[] = ['estado' => (string) $state->nombre, 'total' => (int) $count];
        }
        $records = $baseQuery->paginate($filters['per_page'] ?? 50)->withQueryString();

        return response()->json(['success' => true, 'data' => [
            'filtros_aplicados' => array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== ''),
            'total' => $total,
            'por_estado' => $byState,
            'registros' => SolicitudResource::collection($records->getCollection()),
            'paginacion' => [
                'current_page' => $records->currentPage(), 'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(), 'total' => $records->total(),
            ],
        ]]);
    }
}
