<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\EstudianteCarrera;
use App\Models\TramiteProceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $assignments = $request->user()->carrerasComoEstudiante()
            ->with(['coordinadorCarrera.carrera', 'coordinadorCarrera.coordinador.roles'])->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => [
            'tramites' => TramiteProceso::query()->with(['tipoTramite', 'tipoProceso'])->orderBy('id')->get()
                ->map(fn (TramiteProceso $process): array => [
                    'id' => $process->id, 'tipo_tramite' => $process->tipoTramite->nombre, 'tipo_proceso' => $process->tipoProceso->nombre,
                ]),
            'asignaciones' => $assignments->map(fn (EstudianteCarrera $assignment): array => [
                'coordinador_carrera_id' => $assignment->coordinador_carrera_id,
                'carrera' => ['id' => $assignment->coordinadorCarrera->carrera->id, 'nombre' => $assignment->coordinadorCarrera->carrera->nombre],
                'coordinador' => ['id' => $assignment->coordinadorCarrera->coordinador->id, 'nombres_completos' => $assignment->coordinadorCarrera->coordinador->nombres_completos],
                'disponible' => (bool) ($assignment->coordinadorCarrera->coordinador->cuenta_activa && $assignment->coordinadorCarrera->coordinador->hasRole('coordinador')),
            ]),
        ]]);
    }
}
