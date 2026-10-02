<?php

namespace App\Services;

use App\Models\Solicitud;
use Illuminate\Database\Eloquent\Builder;

class SolicitudQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Solicitud>
     */
    public function build(array $filters, ?Builder $query = null): Builder
    {
        return ($query ?? Solicitud::query())
            ->with([
                'carrera',
                'estudiante.roles',
                'coordinador.roles',
                'tramiteProceso.tipoTramite',
                'tramiteProceso.tipoProceso',
                'ultimoHistorialEstado.estadoSolicitud',
                'resolucion.coordinador',
            ])
            ->when($filters['estado'] ?? null, function (Builder $query, string $estado): void {
                $query->whereHas('ultimoHistorialEstado.estadoSolicitud', function (Builder $stateQuery) use ($estado): void {
                    $stateQuery->when(
                        is_numeric($estado),
                        fn (Builder $builder) => $builder->whereKey((int) $estado),
                        fn (Builder $builder) => $builder->where('nombre', $estado),
                    );
                });
            })
            ->when($filters['carrera'] ?? null, function (Builder $query, int|string $carreraId): void {
                $query->where(function (Builder $careerQuery) use ($carreraId): void {
                    $careerQuery
                        ->where('carrera_id', $carreraId)
                        ->orWhere(function (Builder $legacyQuery) use ($carreraId): void {
                            $legacyQuery->whereNull('carrera_id')->where(function (Builder $legacyCareerQuery) use ($carreraId): void {
                                $legacyCareerQuery
                                    ->whereHas('documentos.documentoRequerido', fn (Builder $builder) => $builder->where('carrera_id', $carreraId))
                                    ->orWhereHas('estudiante.carrerasComoEstudiante.coordinadorCarrera', fn (Builder $builder) => $builder->where('carrera_id', $carreraId));
                            });
                        });
                });
            })
            ->when($filters['tipo_tramite'] ?? null, function (Builder $query, string $tipoTramite): void {
                $query->whereHas('tramiteProceso.tipoTramite', function (Builder $typeQuery) use ($tipoTramite): void {
                    $typeQuery->when(
                        is_numeric($tipoTramite),
                        fn (Builder $builder) => $builder->whereKey((int) $tipoTramite),
                        fn (Builder $builder) => $builder->where('nombre', $tipoTramite),
                    );
                });
            })
            ->when($filters['tipo_proceso'] ?? null, function (Builder $query, string $tipoProceso): void {
                $query->whereHas('tramiteProceso.tipoProceso', function (Builder $typeQuery) use ($tipoProceso): void {
                    $typeQuery->when(
                        is_numeric($tipoProceso),
                        fn (Builder $builder) => $builder->whereKey((int) $tipoProceso),
                        fn (Builder $builder) => $builder->where('nombre', $tipoProceso),
                    );
                });
            })
            ->when($filters['estudiante'] ?? $filters['search'] ?? null, function (Builder $query, string $student): void {
                $query->whereHas('estudiante', fn (Builder $studentQuery) => $studentQuery
                    ->where(fn (Builder $searchQuery) => $searchQuery
                        ->where('nombres_completos', 'like', "%{$student}%")
                        ->orWhere('cedula', 'like', "%{$student}%")
                        ->orWhere('email', 'like', "%{$student}%")));
            })
            ->when($filters['coordinador'] ?? null, function (Builder $query, string $coordinator): void {
                $query->whereHas('coordinador', fn (Builder $coordinatorQuery) => $coordinatorQuery
                    ->where('nombres_completos', 'like', "%{$coordinator}%")
                    ->orWhere('cedula', 'like', "%{$coordinator}%")
                    ->orWhere('email', 'like', "%{$coordinator}%"));
            })
            ->when($filters['fecha_desde'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['fecha_hasta'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id');
    }
}
