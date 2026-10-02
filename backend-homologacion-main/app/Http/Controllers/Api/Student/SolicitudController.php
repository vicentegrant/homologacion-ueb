<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\SolicitudIndexRequest;
use App\Http\Requests\Api\Student\StoreSolicitudRequest;
use App\Http\Requests\Api\Student\UpdateSolicitudRequest;
use App\Http\Resources\Api\StudentSolicitudResource;
use App\Models\Solicitud;
use App\Services\StudentSolicitudService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SolicitudController extends Controller
{
    public function index(SolicitudIndexRequest $request, StudentSolicitudService $service): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $records = $request->user()->solicitudesComoEstudiante()->with($service->relations())
            ->when($filters['estado'] ?? null, fn (Builder $query, string $state): Builder => $query
                ->whereHas('ultimoHistorialEstado.estadoSolicitud', fn (Builder $query): Builder => $query->where('nombre', $state)))
            ->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return StudentSolicitudResource::collection($records)->additional(['success' => true]);
    }

    public function store(StoreSolicitudRequest $request, StudentSolicitudService $service): JsonResponse
    {
        return $this->response($service->create($request->user(), $request->validated()), $service, 201);
    }

    public function show(Request $request, int $solicitud, StudentSolicitudService $service): JsonResponse
    {
        return $this->response($service->owned($request->user(), $solicitud), $service);
    }

    public function update(UpdateSolicitudRequest $request, int $solicitud, StudentSolicitudService $service): JsonResponse
    {
        return $this->response($service->update($request->user(), $solicitud, $request->validated()), $service);
    }

    public function submit(Request $request, int $solicitud, StudentSolicitudService $service): JsonResponse
    {
        return $this->response($service->submit($request->user(), $solicitud), $service);
    }

    private function response(Solicitud $solicitud, StudentSolicitudService $service, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => StudentSolicitudResource::make($solicitud->load($service->relations()))], $status);
    }
}
