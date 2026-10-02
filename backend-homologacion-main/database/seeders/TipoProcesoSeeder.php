<?php

namespace Database\Seeders;

use App\Models\TipoProceso;
use Illuminate\Database\Seeder;

class TipoProcesoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['malla_a_malla', 'carreras_facultad', 'otra_universidad'] as $nombre) {
            TipoProceso::query()->updateOrCreate(['nombre' => $nombre]);
        }
    }
}
