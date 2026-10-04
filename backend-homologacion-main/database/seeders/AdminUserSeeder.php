<?php

namespace Database\Seeders;

use App\Models\User;
use App\Rules\IdentificationNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array{name: string, cedula: ?string, email: ?string, phone: ?string, password: ?string} $adminData */
        $adminData = config('app.initial_admin');

        if (! $adminData['cedula'] || ! $adminData['email'] || ! $adminData['phone']) {
            $this->command?->warn('Administrador inicial omitido: completa las variables INITIAL_ADMIN_* requeridas.');

            return;
        }

        Validator::make($adminData, ['cedula' => ['required', 'string', new IdentificationNumber('cedula')]])->validate();
        // Un administrador existente conserva sus credenciales; uno nuevo debe cambiarlas.
        $admin = User::firstOrCreate(['email' => $adminData['email']], [
            'nombres_completos' => $adminData['name'],
            'cedula' => $adminData['cedula'],
            'numero_celular' => $adminData['phone'],
            'password' => $adminData['cedula'],
        ]);
        if ($admin->wasRecentlyCreated) {
            $admin->forceFill(['must_change_password' => true])->save();
        }

        $admin->assignRole('administrador');
    }
}
