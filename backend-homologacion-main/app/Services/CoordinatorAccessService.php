<?php

namespace App\Services;

use App\Models\AsignaturaCredito;
use App\Models\ComparacionAsignatura;
use App\Models\MallaCurricular;
use App\Models\Solicitud;
use App\Models\SolicitudDocumento;
use App\Models\TemaSilaboAsignatura;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CoordinatorAccessService
{
    /** @return list<int> */
    public function careerIds(User $coordinator): array
    {
        return $coordinator->carrerasCoordinadas()->pluck('carreras.id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @return Builder<Solicitud> */
    public function solicitudes(User $coordinator): Builder
    {
        return Solicitud::query()->whereIn('carrera_id', $this->careerIds($coordinator));
    }

    public function solicitud(User $coordinator, int $id, bool $lock = false): Solicitud
    {
        return $this->solicitudes($coordinator)
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->findOrFail($id);
    }

    /** @return Builder<User> */
    public function students(User $coordinator): Builder
    {
        return User::query()->role('estudiante')->where(function (Builder $query) use ($coordinator): void {
            $query->whereHas('carrerasComoEstudiante.coordinadorCarrera', fn (Builder $assignment) => $assignment
                ->where('coordinador_id', $coordinator->id))
                ->orWhereHas('solicitudesComoEstudiante', fn (Builder $solicitudes) => $solicitudes
                    ->whereIn('carrera_id', $this->careerIds($coordinator)));
        });
    }

    public function student(User $coordinator, int $id): User
    {
        return $this->students($coordinator)->findOrFail($id);
    }

    public function document(User $coordinator, int $id, bool $lock = false): SolicitudDocumento
    {
        return SolicitudDocumento::query()
            ->whereHas('solicitud', fn (Builder $query) => $query->whereIn('carrera_id', $this->careerIds($coordinator)))
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->findOrFail($id);
    }

    /** @return Builder<MallaCurricular> */
    public function curricula(User $coordinator): Builder
    {
        $careerIds = $this->careerIds($coordinator);

        return MallaCurricular::query()->where(function (Builder $query) use ($coordinator, $careerIds): void {
            $query->where(fn (Builder $institutional) => $institutional
                ->where('tipo', 'institucional')->whereIn('carrera_id', $careerIds))
                ->orWhere(fn (Builder $origin) => $origin
                    ->where('tipo', 'origen')
                    ->whereHas('estudiante.carrerasComoEstudiante.coordinadorCarrera', fn (Builder $assignment) => $assignment
                        ->where('coordinador_id', $coordinator->id)));
        });
    }

    public function curriculum(User $coordinator, int $id): MallaCurricular
    {
        return $this->curricula($coordinator)->findOrFail($id);
    }

    public function subject(User $coordinator, int $id): AsignaturaCredito
    {
        return AsignaturaCredito::query()
            ->whereIn('malla_curricular_id', $this->curricula($coordinator)->select('id'))
            ->findOrFail($id);
    }

    public function syllabusTopic(User $coordinator, int $id): TemaSilaboAsignatura
    {
        return TemaSilaboAsignatura::query()
            ->whereHas('asignatura', fn (Builder $query) => $query
                ->whereIn('malla_curricular_id', $this->curricula($coordinator)->select('id')))
            ->findOrFail($id);
    }

    public function comparison(User $coordinator, int $id): ComparacionAsignatura
    {
        return ComparacionAsignatura::query()
            ->whereHas('solicitud', fn (Builder $query) => $query->whereIn('carrera_id', $this->careerIds($coordinator)))
            ->findOrFail($id);
    }

    public function ensureCareer(User $coordinator, int $careerId): void
    {
        abort_unless(in_array($careerId, $this->careerIds($coordinator), true), 403, 'La carrera no está asignada al coordinador.');
    }
}
