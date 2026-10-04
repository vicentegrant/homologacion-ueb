<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $creator): User
    {
        return DB::transaction(function () use ($data, $creator): User {
            $role = Role::query()->findOrFail($data['rol_id']);
            $attributes = Arr::except($data, ['rol_id', 'password']);
            $temporaryPassword = Str::password(20);
            $attributes['password'] = Hash::make($temporaryPassword);
            $attributes['creador_id'] = $creator->getKey();
            $attributes['cuenta_activa'] = true;

            $user = User::query()->create($attributes);
            $user->assignRole($role);
            $user->forceFill(['must_change_password' => true, 'temporary_password_expires_at' => now()->addHours(config('auth.temporary_password_hours'))])->save();
            $user->notify(new TemporaryPasswordNotification($temporaryPassword));

            return $user->load(['roles', 'creador', 'carrerasCoordinadas']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $roleId = Arr::pull($data, 'rol_id');

            unset($data['password']);

            $user->update($data);

            if ($roleId !== null) {
                $user->syncRoles([Role::query()->findOrFail($roleId)]);
            }

            return $user->load(['roles', 'creador', 'carrerasCoordinadas']);
        });
    }
}
