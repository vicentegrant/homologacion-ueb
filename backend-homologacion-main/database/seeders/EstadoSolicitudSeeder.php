<?php

namespace Database\Seeders;

use App\Models\EstadoSolicitud;
use Illuminate\Database\Seeder;

class EstadoSolicitudSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['pendiente', 'en_revision', 'observado', 'en_proceso', 'aprobado', 'en_consejo', 'listo', 'rechazado'] as $nombre) {
            EstadoSolicitud::query()->updateOrCreate(['nombre' => $nombre]);
        }
    }
}
