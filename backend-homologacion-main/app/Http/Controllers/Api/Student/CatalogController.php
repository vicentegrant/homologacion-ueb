<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CatalogEntryResource;
use App\Models\DocumentoRequeridoProceso;
use App\Models\EstudianteCarrera;
use App\Models\TramiteProceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $assignments = $request->user()->carrerasComoEstudiante()
            ->with(['coordinadorCarrera.carrera.facultad', 'coordinadorCarrera.coordinador.roles', 'modalidad'])->orderBy('id')->get();
        $careerIds = $assignments->map(fn (EstudianteCarrera $assignment): int => $assignment->coordinadorCarrera->carrera_id)->unique()->all();
        $background = $request->user()->antecedentesAcademicos()->latest('id')->first();

        return response()->json(['success' => true, 'data' => [
            'procedencia_estudios' => $background?->universidad_origen,
            'procedencia_configurada' => $background?->procedencia !== null,
            'requisitos' => CatalogEntryResource::collection(DocumentoRequeridoProceso::query()->where('activo', true)
                ->where(fn ($query) => $query->whereNull('carrera_id')->orWhereIn('carrera_id', $careerIds))->orderBy('nombre_documento')->get()),
            'tramites' => TramiteProceso::query()->with(['tipoTramite', 'tipoProceso'])->orderBy('id')->get()
                ->map(fn (TramiteProceso $process): array => [
                    'id' => $process->id, 'tipo_tramite' => $process->tipoTramite->nombre, 'tipo_proceso' => $process->tipoProceso->nombre,
                ]),
            'asignaciones' => $assignments->map(fn (EstudianteCarrera $assignment): array => [
                'coordinador_carrera_id' => $assignment->coordinador_carrera_id,
                'carrera' => ['id' => $assignment->coordinadorCarrera->carrera->id, 'nombre' => $assignment->coordinadorCarrera->carrera->nombre],
                'coordinador' => ['id' => $assignment->coordinadorCarrera->coordinador->id, 'nombres_completos' => $assignment->coordinadorCarrera->coordinador->nombres_completos],
                'disponible' => (bool) ($assignment->coordinadorCarrera->coordinador->cuenta_activa && $assignment->coordinadorCarrera->coordinador->hasRole('coordinador')
                    && $assignment->coordinadorCarrera->carrera->activa
                    && (! $assignment->coordinadorCarrera->carrera->facultad_id || $assignment->coordinadorCarrera->carrera->facultad->activa)
                    && (! $assignment->modalidad_id || $assignment->modalidad->activa)),
            ]),
        ]]);
    }
}
