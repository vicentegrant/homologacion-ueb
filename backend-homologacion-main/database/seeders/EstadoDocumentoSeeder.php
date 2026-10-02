<?php

namespace Database\Seeders;

use App\Models\EstadoDocumento;
use Illuminate\Database\Seeder;

class EstadoDocumentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['pendiente', 'presentado', 'aprobado', 'observado'] as $nombre) {
            EstadoDocumento::query()->updateOrCreate(['nombre' => $nombre]);
        }
    }
}
