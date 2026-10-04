<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentAuthSeeder extends Seeder
{
    /**
     * Crear o reiniciar tres cuentas reservadas para probar la autenticación.
     */
    public function run(): void
    {
        // Incluso con --force, estas credenciales públicas nunca se crean en producción.
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Este seeder solo se permite en local/testing.');
        }

        DB::transaction(function (): void {
            $this->call(RoleSeeder::class);
            $accounts = [
                ['administrador', 'Administrador de prueba', '0100000009'],
                ['coordinador', 'Coordinador de prueba', '0200000008'],
                ['estudiante', 'Estudiante de prueba', '0300000007'],
            ];
            foreach ($accounts as [$role, $name, $identification]) {
                // Solo se reinician las tres cuentas reservadas para esta prueba.
                $user = User::firstOrNew(['email' => $role.'.auth@example.test']);
                $user->forceFill([
                    'nombres_completos' => $name,
                    'tipo_identificacion' => 'cedula',
                    'cedula' => $identification,
                    'numero_celular' => '0990000000',
                    'password' => Hash::make($identification),
                    'must_change_password' => true,
                    'password_changed_at' => null,
                    'temporary_password_expires_at' => null,
                    'cuenta_activa' => true,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->syncRoles([$role]);
                $user->tokens()->delete();
                Password::deleteToken($user);
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });
        $this->command?->info('Tres cuentas de autenticación reiniciadas. Consulte docs/prueba-autenticacion.md.');
    }
}
