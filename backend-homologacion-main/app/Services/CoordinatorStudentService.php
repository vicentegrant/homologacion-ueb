<?php

namespace App\Services;

use App\Models\AntecedenteAcademico;
use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            // Una carrera externa no se incorpora al catálogo de carreras de la UEB.
            $provenance = $data['procedencia'] ?? null;
            $origin = $provenance === 'externa' ? null : Carrera::query()->with('facultad')->findOrFail($data['carrera_origen_id']);
            if ($provenance === 'interna' && (! $origin->activa || ($origin->facultad_id && ! $origin->facultad->activa))) {
                throw ValidationException::withMessages(['carrera_origen_id' => 'Seleccione una carrera de origen activa de la UEB.']);
            }
            $academicData = [
                'procedencia' => $provenance,
                'carrera_origen_id' => $provenance === 'interna' ? $origin->id : null,
                'carrera_origen' => $origin?->nombre ?? trim($data['carrera_origen']),
                'universidad_origen' => $provenance === 'interna' ? AntecedenteAcademico::UEB_NAME : trim($data['universidad_origen']),
                'tipo_institucion' => $provenance === 'interna' ? 'publica' : $data['tipo_institucion'],
                'periodo_cursado' => $data['periodo_cursado'],
            ];
            if ($provenance === 'externa' && in_array(Str::lower(Str::ascii($academicData['universidad_origen'])), ['ueb', 'universidad estatal de bolivar'], true)) {
                throw ValidationException::withMessages(['universidad_origen' => 'Para estudios realizados en la UEB, seleccione homologación interna.']);
            }
            if ($id) {
                $student = $this->editable($coordinator, $id);
                $student = User::query()->lockForUpdate()->findOrFail($student->id);
                $current = $student->carrerasComoEstudiante()->whereHas('coordinadorCarrera', fn ($q) => $q->where('coordinador_id', $coordinator->id))->get();
                $background = $student->antecedentesAcademicos()->latest('id')->first();
                $changed = $current->contains(fn ($a) => $a->coordinador_carrera_id !== $assignment->id || $a->modalidad_id !== (int) $data['modalidad_id'])
                    || ($background && ($background->carrera_origen !== $academicData['carrera_origen']
                        || $background->universidad_origen !== $academicData['universidad_origen']
                        || $background->tipo_institucion !== $academicData['tipo_institucion']
                        || $background->procedencia !== $provenance));
                if ($changed) {
                    abort_if($student->solicitudesComoEstudiante()->whereHas('ultimoHistorialEstado.estadoSolicitud', fn ($q) => $q->whereNotIn('nombre', ['listo', 'rechazado']))->exists(), 409, 'No puede cambiar la procedencia, institución o carreras mientras existe una solicitud activa.');
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
            $background = $student->antecedentesAcademicos()->latest('id')->first();
            if ($background) {
                $background->update($academicData);
            } else {
                $student->antecedentesAcademicos()->create($academicData);
            }

            return $student->load(['antecedentesAcademicos', 'carrerasComoEstudiante.coordinadorCarrera.carrera', 'carrerasComoEstudiante.coordinadorCarrera.coordinador', 'carrerasComoEstudiante.modalidad']);
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
