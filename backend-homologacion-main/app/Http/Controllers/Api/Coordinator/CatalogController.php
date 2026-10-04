<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AntecedenteAcademico;
use App\Models\Carrera;
use App\Models\EstadoDocumento;
use App\Models\EstadoSolicitud;
use App\Models\TramiteProceso;
use App\Models\User;
use App\Services\SolicitudWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __invoke(Request $request, SolicitudWorkflowService $workflow): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'carreras' => $this->careers($request->user()),
            'universidad_interna' => AntecedenteAcademico::UEB_NAME,
            'carreras_origen' => Carrera::query()->where('activa', true)
                ->where(fn ($query) => $query->whereNull('facultad_id')->orWhereHas('facultad', fn ($faculty) => $faculty->where('activa', true)))
                ->with('facultad')->orderBy('nombre')->get()
                ->map(fn (Carrera $career): array => ['id' => $career->id, 'nombre' => $career->nombre, 'facultad' => $career->facultad?->nombre]),
            'estados_solicitud' => EstadoSolicitud::query()->orderBy('id')->get(['id', 'nombre']),
            'estados_documento' => EstadoDocumento::query()->orderBy('id')->get(['id', 'nombre']),
            'tramites' => TramiteProceso::query()->with(['tipoTramite', 'tipoProceso'])->orderBy('id')->get()->map(fn (TramiteProceso $process): array => [
                'id' => $process->id,
                'tipo_tramite' => $process->tipoTramite->nombre,
                'tipo_proceso' => $process->tipoProceso->nombre,
            ]),
            'niveles_ciclo' => ['primero', 'segundo', 'tercero', 'cuarto', 'quinto', 'sexto', 'septimo', 'octavo', 'noveno', 'decimo'],
            'transiciones' => $workflow->transitions(),
        ]]);
    }

    /** @return list<array{id: int, nombre: string}> */
    private function careers(User $coordinator): array
    {
        return $coordinator->carrerasCoordinadas()->orderBy('nombre')
            ->with(['modalidades', 'facultad'])->get(['carreras.*'])
            ->map(fn (Carrera $career): array => ['id' => $career->id, 'nombre' => $career->nombre, 'facultad' => $career->facultad?->nombre, 'activa' => $career->activa && (! $career->facultad_id || $career->facultad->activa), 'modalidades' => $career->modalidades->map(fn ($m): array => ['id' => $m->id, 'nombre' => $m->nombre, 'activa' => $m->activa])->all()])
            ->values()->all();
    }
}
