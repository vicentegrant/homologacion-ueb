<?php

namespace App\Services;

use App\Models\AsignaturaCredito;
use App\Models\ComparacionAsignatura;
use App\Models\ResultadoSolicitud;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcademicAnalysisService
{
    public function __construct(
        private CoordinatorAccessService $access,
        private SolicitudWorkflowService $workflow,
    ) {}

    /** @param array{asignatura_origen_id: int, asignatura_destino_id: int, porcentaje_coincidencia: float|int|string, observacion?: string|null} $data */
    public function createComparison(User $coordinator, Solicitud $solicitud, array $data): ComparacionAsignatura
    {
        return DB::transaction(function () use ($coordinator, $solicitud, $data): ComparacionAsignatura {
            $locked = $this->access->solicitud($coordinator, $solicitud->id, true);
            abort_unless($this->workflow->currentState($locked) === 'en_proceso', 409, 'La solicitud no está en etapa de análisis académico.');
            [$origin, $destination] = $this->validatedSubjects($locked, $data['asignatura_origen_id'], $data['asignatura_destino_id']);
            abort_if($locked->comparacionesAsignaturas()->where([
                'asignatura_origen_id' => $origin->id,
                'asignatura_destino_id' => $destination->id,
            ])->exists(), 409, 'La comparación ya está registrada.');

            return $locked->comparacionesAsignaturas()->create([
                'asignatura_origen_id' => $origin->id,
                'asignatura_destino_id' => $destination->id,
                'porcentaje_coincidencia' => $data['porcentaje_coincidencia'],
                'observacion' => $data['observacion'] ?? null,
            ]);
        })->load(['asignaturaOrigen.mallaCurricular', 'asignaturaDestino.mallaCurricular']);
    }

    /** @param array{asignatura_origen_id: int, asignatura_destino_id: int, porcentaje_coincidencia: float|int|string, observacion?: string|null} $data */
    public function updateComparison(User $coordinator, int $id, array $data): ComparacionAsignatura
    {
        return DB::transaction(function () use ($coordinator, $id, $data): ComparacionAsignatura {
            $comparison = $this->access->comparison($coordinator, $id);
            $solicitud = $this->access->solicitud($coordinator, $comparison->solicitud_id, true);
            abort_unless($this->workflow->currentState($solicitud) === 'en_proceso', 409, 'La solicitud no está en etapa de análisis académico.');
            $this->validatedSubjects($solicitud, $data['asignatura_origen_id'], $data['asignatura_destino_id']);
            abort_if($solicitud->comparacionesAsignaturas()
                ->where('asignatura_origen_id', $data['asignatura_origen_id'])
                ->where('asignatura_destino_id', $data['asignatura_destino_id'])
                ->where('id', '!=', $comparison->id)
                ->exists(), 409, 'La comparación ya está registrada.');
            $comparison->update($data);

            return $comparison;
        })->load(['asignaturaOrigen.mallaCurricular', 'asignaturaDestino.mallaCurricular']);
    }

    /** @param array{conclusion_general: string, total_creditos_reconocidos: int} $data */
    public function createResult(User $coordinator, Solicitud $solicitud, array $data): ResultadoSolicitud
    {
        return DB::transaction(function () use ($coordinator, $solicitud, $data): ResultadoSolicitud {
            $locked = $this->access->solicitud($coordinator, $solicitud->id, true);
            abort_unless($this->workflow->currentState($locked) === 'en_proceso', 409, 'La solicitud no está en etapa de análisis académico.');
            abort_unless($this->workflow->requirementsApproved($locked), 409, 'Los requisitos previos no están aprobados.');
            abort_unless($locked->comparacionesAsignaturas()->exists(), 409, 'Debe registrar al menos una comparación académica.');
            abort_if($locked->resultado()->exists(), 409, 'La solicitud ya tiene un resultado académico.');

            $result = $locked->resultado()->create([
                ...$data,
                'coordinador_id' => $coordinator->id,
            ]);
            $target = $data['conclusion_general'] === 'rechazada' ? 'rechazado' : 'aprobado';
            $message = $target === 'rechazado' ? 'Resultado académico rechazado.' : 'Resultado académico aprobado.';
            $this->workflow->transition($locked, $target, $coordinator, $message);

            return $result;
        });
    }

    /** @return array{AsignaturaCredito, AsignaturaCredito} */
    private function validatedSubjects(Solicitud $solicitud, int $originId, int $destinationId): array
    {
        abort_if($originId === $destinationId, 422, 'Las asignaturas de origen y destino deben ser diferentes.');
        $origin = AsignaturaCredito::query()->with('mallaCurricular')->findOrFail($originId);
        $destination = AsignaturaCredito::query()->with('mallaCurricular')->findOrFail($destinationId);
        abort_unless($origin->mallaCurricular->tipo === 'origen' && $origin->mallaCurricular->estudiante_id === $solicitud->estudiante_id, 422, 'La asignatura de origen no corresponde al estudiante de la solicitud.');
        abort_unless($destination->mallaCurricular->tipo === 'institucional', 422, 'La asignatura de destino debe pertenecer a una malla institucional.');
        abort_unless($destination->mallaCurricular->carrera_id === $solicitud->carrera_id, 403, 'La asignatura de destino no corresponde a la carrera autorizada.');

        return [$origin, $destination];
    }
}
