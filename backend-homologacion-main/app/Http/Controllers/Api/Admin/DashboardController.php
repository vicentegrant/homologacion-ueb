<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EstadoSolicitud;
use App\Models\Role;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $roles = Role::query()->orderBy('nombre')->get()->map(fn (Role $role): array => [
            'rol' => $role->nombre,
            'total' => User::query()->role($role)->count(),
        ]);
        $states = EstadoSolicitud::query()->orderBy('nombre')->get()->map(fn (EstadoSolicitud $state): array => [
            'estado' => $state->nombre,
            'total' => Solicitud::query()
                ->whereHas('ultimoHistorialEstado', fn (Builder $query) => $query->where('estado_solicitud_id', $state->getKey()))
                ->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'usuarios' => [
                    'total' => User::query()->count(),
                    'por_rol' => $roles,
                    'activos' => User::query()->where('cuenta_activa', true)->count(),
                    'inactivos' => User::query()->where('cuenta_activa', false)->count(),
                ],
                'solicitudes' => [
                    'total' => Solicitud::query()->count(),
                    'por_estado' => $states,
                ],
            ],
        ]);
    }
}
