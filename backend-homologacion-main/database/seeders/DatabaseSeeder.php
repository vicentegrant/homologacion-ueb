<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call([
            TipoTramiteSeeder::class,
            TipoProcesoSeeder::class,
            EstadoDocumentoSeeder::class,
            EstadoSolicitudSeeder::class,
            TramiteProcesoSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $user = User::firstOrCreate(['email' => 'test@example.com'], [
                'nombres_completos' => 'Test User',
                'cedula' => '9999999999',
                'numero_celular' => '0999999999',
                'password' => 'password',
            ]);
            $user->assignRole('estudiante');
        }

        $this->call(AdminUserSeeder::class);
    }
}
