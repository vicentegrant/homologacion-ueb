<?php

namespace Database\Seeders;

use App\Models\TipoProceso;
use App\Models\TipoTramite;
use App\Models\TramiteProceso;
use Illuminate\Database\Seeder;

class TramiteProcesoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $combinaciones = [
            ['reconocimiento', 'malla_a_malla'],
            ['homologacion', 'carreras_facultad'],
            ['homologacion', 'otra_universidad'],
        ];

        foreach ($combinaciones as [$tramite, $proceso]) {
            TramiteProceso::query()->updateOrCreate([
                'tipo_tramite_id' => TipoTramite::query()->where('nombre', $tramite)->firstOrFail()->id,
                'tipo_proceso_id' => TipoProceso::query()->where('nombre', $proceso)->firstOrFail()->id,
            ]);
        }
    }
}
