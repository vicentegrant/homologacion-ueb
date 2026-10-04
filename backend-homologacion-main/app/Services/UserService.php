<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use App\Rules\IdentificationNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserService
{
    public function delete(User $user, User $administrator): void
    {
        abort_if($administrator->is($user), 409, 'No puede eliminar su propia cuenta.');

        try {
            DB::transaction(function () use ($user): void {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                $hasHistory = $user->solicitudesComoEstudiante()->exists()
                    || $user->solicitudesComoCoordinador()->exists()
                    || $user->antecedentesAcademicos()->exists()
                    || $user->mallasCreadas()->exists()
                    || $user->mallasDeOrigen()->exists()
                    || $user->cambiosEstadoRealizados()->exists()
                    || $user->coordinaciones()->whereHas('estudiantes')->exists();
                abort_if($hasHistory, 409, 'El usuario tiene registros académicos o historial. Desactive su cuenta para conservarlos.');

                $user->coordinaciones()->delete();
                $user->tokens()->delete();
                $user->notifications()->delete();
                Password::deleteToken($user);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23503', '23001'], true)) {
                abort(409, 'El usuario tiene registros relacionados. Desactive su cuenta para conservar su historial.');
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $creator): User
    {
        // La identificación se valida antes de utilizarla como credencial inicial.
        Validator::make($data, [
            'tipo_identificacion' => ['sometimes', 'in:cedula,pasaporte'],
            'cedula' => ['required', 'string', new IdentificationNumber($data['tipo_identificacion'] ?? 'cedula')],
        ])->validate();
        $temporaryPassword = $data['cedula'];
        $user = DB::transaction(function () use ($data, $creator, $temporaryPassword): User {
            $role = Role::query()->findOrFail($data['rol_id']);
            $attributes = Arr::except($data, ['rol_id', 'password', 'carrera_ids']);
            $attributes['password'] = Hash::make($temporaryPassword);
            $attributes['creador_id'] = $creator->getKey();
            $attributes['cuenta_activa'] = true;

            $user = User::query()->create($attributes);
            $user->assignRole($role);
            $user->forceFill(['must_change_password' => true, 'password_changed_at' => null, 'temporary_password_expires_at' => null])->save();
            if (array_key_exists('carrera_ids', $data)) {
                $this->assignCoordinatorCareers($user, $data['carrera_ids']);
            }

            return $user->load(['roles', 'creador', 'carrerasCoordinadas']);
        });
        $user->credentialsEmailSent = $this->sendCredentials($user, $temporaryPassword);

        return $user;
    }

    public function resendCredentials(User $user, User $creator): bool
    {
        abort_unless($user->creador_id === $creator->id, 403, 'Solo quien creó esta cuenta puede reenviar sus credenciales.');
        $temporaryPassword = Str::password(20).'a1';
        DB::transaction(function () use ($user, $temporaryPassword): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->cuenta_activa, 409, 'Active la cuenta antes de reenviar sus credenciales.');
            $user->forceFill(['password' => Hash::make($temporaryPassword), 'password_temporal' => true, 'temporary_password_expires_at' => now()->addHours(config('auth.temporary_password_hours')), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            Password::deleteToken($user);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return $this->sendCredentials($user->refresh(), $temporaryPassword);
    }

    private function sendCredentials(User $user, string $password): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if (! in_array($transport, ['smtp', 'array', 'sendmail', 'ses', 'postmark', 'resend', 'mailgun', 'mailtrap-sdk'], true)) {
            return false;
        }
        try {
            $user->notify(new TemporaryPasswordNotification($password));

            return true;
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar el correo de credenciales.', ['user_id' => $user->id, 'exception_type' => $exception::class]);

            return false;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $roleId = Arr::pull($data, 'rol_id');
            $careerIds = Arr::pull($data, 'carrera_ids');

            unset($data['password']);

            $user->update($data);

            if ($roleId !== null) {
                $user->syncRoles([Role::query()->findOrFail($roleId)]);
            }
            if ($careerIds !== null) {
                abort_unless($this->assignCoordinatorCareers($user, $careerIds), 409, 'No puede retirar carreras con estudiantes asignados. Reasigne los estudiantes primero.');
            }

            return $user->load(['roles', 'creador', 'carrerasCoordinadas']);
        });
    }

    /**
     * Guarda las carreras sin retirar coordinaciones con estudiantes ni agregar oferta inactiva.
     *
     * @param  array<int, int|string>  $careerIds
     */
    public function assignCoordinatorCareers(User $coordinator, array $careerIds): bool
    {
        if (! $coordinator->hasRole('coordinador')) {
            throw ValidationException::withMessages(['carrera_ids' => 'Solo los coordinadores pueden tener carreras asignadas.']);
        }

        return DB::transaction(function () use ($coordinator, $careerIds): bool {
            $assignments = $coordinator->coordinaciones()->lockForUpdate()->get();
            $newCareerIds = array_diff($careerIds, $assignments->pluck('carrera_id')->all());
            $unavailable = Carrera::query()->whereIn('id', $newCareerIds)
                ->where(function ($query) {
                    $query->where('activa', false)->orWhereHas('facultad', fn ($faculty) => $faculty->where('activa', false));
                })->exists();
            if ($unavailable) {
                throw ValidationException::withMessages(['carrera_ids' => 'Solo puede asignar nuevas carreras activas de facultades activas.']);
            }
            foreach ($assignments as $assignment) {
                if (! in_array($assignment->carrera_id, $careerIds) && $assignment->estudiantes()->exists()) {
                    return false;
                }
            }
            $coordinator->carrerasCoordinadas()->sync($careerIds);

            return true;
        });
    }
}
