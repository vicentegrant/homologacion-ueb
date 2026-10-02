<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\TramiteProceso;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class StudentDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Los datos de demostración solo se permiten en local y testing.');
        }

        $this->call(DatabaseSeeder::class);
        $student = User::query()->where('email', 'test@example.com')->firstOrFail();
        $coordinator = User::query()->firstOrCreate(['email' => 'coordinador-demo@example.com'], [
            'nombres_completos' => 'Coordinador de demostración', 'cedula' => 'DEMO-COORD',
            'numero_celular' => '0000000000', 'password' => Str::random(40),
        ]);
        $coordinator->assignRole('coordinador');
        $career = Carrera::query()->firstOrCreate(['nombre' => '[DEMO] Ingeniería de Software']);
        $assignment = CoordinadorCarrera::query()->firstOrCreate(['coordinador_id' => $coordinator->id, 'carrera_id' => $career->id]);
        $student->carrerasComoEstudiante()->firstOrCreate(['coordinador_carrera_id' => $assignment->id]);

        foreach (TramiteProceso::query()->get() as $process) {
            foreach (['Identificación', 'Certificado de notas'] as $name) {
                DocumentoRequeridoProceso::query()->firstOrCreate([
                    'tramite_proceso_id' => $process->id, 'carrera_id' => $career->id,
                    'nombre_documento' => '[DEMO] '.$name,
                ], ['descripcion' => 'Requisito de ejemplo para pruebas locales; no representa normativa institucional.']);
            }
        }
    }
}
