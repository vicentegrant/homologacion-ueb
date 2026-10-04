<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CoordinatorStudentService
{
    public function __construct(private CoordinatorAccessService $access, private UserService $users) {}

    /** @param array<string,mixed> $data */
    public function save(User $coordinator, array $data, ?int $id = null): User
    {
        return DB::transaction(function () use ($coordinator, $data, $id): User {
            $assignment = CoordinadorCarrera::query()->where('coordinador_id', $coordinator->id)->where('carrera_id', $data['carrera_id'])->lockForUpdate()->first();
            abort_unless($assignment, 403, 'La carrera de destino no está asignada a este coordinador.');
            $career = Carrera::query()->findOrFail($data['carrera_id']);
            abort_unless($career->activa && (! $career->facultad_id || $career->facultad->activa), 409, 'La carrera o facultad está inactiva.');
            abort_unless($career->modalidades()->whereKey($data['modalidad_id'])->where('activa', true)->exists(), 422, 'La modalidad no está habilitada para esta carrera.');
            $attributes = Arr::only($data, ['nombres_completos', 'tipo_identificacion', 'cedula', 'email', 'numero_celular']);
            if ($id) {
                $student = $this->editable($coordinator, $id);
                $student = User::query()->lockForUpdate()->findOrFail($student->id);
                $current = $student->carrerasComoEstudiante()->whereHas('coordinadorCarrera', fn ($q) => $q->where('coordinador_id', $coordinator->id))->get();
                $changed = $current->contains(fn ($a) => $a->coordinador_carrera_id !== $assignment->id || $a->modalidad_id !== (int) $data['modalidad_id']);
                if ($changed) {
                    abort_if($student->solicitudesComoEstudiante()->whereHas('ultimoHistorialEstado.estadoSolicitud', fn ($q) => $q->whereNotIn('nombre', ['listo', 'rechazado']))->exists(), 409, 'No puede cambiar el destino mientras existe una solicitud activa.');
                }
                $student->update($attributes);
                foreach ($current as $old) {
                    if ($old->coordinador_carrera_id !== $assignment->id) {
                        $old->delete();
                    }
                }
            } else {
                $attributes['rol_id'] = Role::where('nombre', 'estudiante')->firstOrFail()->id;
                $student = $this->users->create($attributes, $coordinator);
            }
            $student->carrerasComoEstudiante()->updateOrCreate(['coordinador_carrera_id' => $assignment->id], ['modalidad_id' => $data['modalidad_id']]);

            return $student->load(['carrerasComoEstudiante.coordinadorCarrera.carrera', 'carrerasComoEstudiante.coordinadorCarrera.coordinador', 'carrerasComoEstudiante.modalidad']);
        });
    }

    public function editable(User $coordinator, int $id): User
    {
        $student = $this->access->student($coordinator, $id);
        abort_unless($student->carrerasComoEstudiante()->whereHas('coordinadorCarrera', fn ($query) => $query->where('coordinador_id', $coordinator->id))->exists(), 404);
        abort_unless($student->roles()->count() === 1 && $student->hasRole('estudiante'), 403, 'Solo puede administrar cuentas exclusivamente de estudiantes.');

        return $student;
    }

    public function status(User $coordinator, int $id, bool $active): User
    {
        return DB::transaction(function () use ($coordinator, $id, $active): User {
            $student = $this->editable($coordinator, $id);
            $student = User::query()->lockForUpdate()->findOrFail($student->id);
            if (! $active) {
                abort_if($student->solicitudesComoEstudiante()->whereHas('ultimoHistorialEstado.estadoSolicitud', fn ($q) => $q->whereNotIn('nombre', ['listo', 'rechazado']))->exists(), 409, 'El estudiante tiene solicitudes activas. Debe resolverlas antes de desactivar su cuenta.');
                $student->tokens()->delete();
            }
            $student->update(['cuenta_activa' => $active]);

            return $student;
        });
    }
}
