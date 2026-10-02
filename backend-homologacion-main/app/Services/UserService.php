<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $creator): User
    {
        return DB::transaction(function () use ($data, $creator): User {
            $role = Role::query()->findOrFail($data['rol_id']);
            $attributes = Arr::except($data, ['rol_id']);
            $attributes['password'] = Hash::make($attributes['password']);
            $attributes['creador_id'] = $creator->getKey();
            $attributes['cuenta_activa'] = true;

            $user = User::query()->create($attributes);
            $user->assignRole($role);

            return $user->load(['roles', 'creador', 'carrerasCoordinadas']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $roleId = Arr::pull($data, 'rol_id');

            if (array_key_exists('password', $data)) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if ($roleId !== null) {
                $user->syncRoles([Role::query()->findOrFail($roleId)]);
            }

            return $user->load(['roles', 'creador', 'carrerasCoordinadas']);
        });
    }
}
