<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array{name: string, cedula: ?string, email: ?string, phone: ?string, password: ?string} $adminData */
        $adminData = config('app.initial_admin');

        if (! $adminData['cedula'] || ! $adminData['email'] || ! $adminData['phone'] || ! $adminData['password']) {
            $this->command?->warn('Administrador inicial omitido: completa las variables INITIAL_ADMIN_* requeridas.');

            return;
        }

        $admin = User::firstOrCreate(['email' => $adminData['email']], [
            'nombres_completos' => $adminData['name'],
            'cedula' => $adminData['cedula'],
            'numero_celular' => $adminData['phone'],
            'password' => $adminData['password'],
        ]);

        $admin->assignRole('administrador');
    }
}
