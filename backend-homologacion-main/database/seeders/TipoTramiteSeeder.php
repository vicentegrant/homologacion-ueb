<?php

namespace Database\Seeders;

use App\Models\TipoTramite;
use Illuminate\Database\Seeder;

class TipoTramiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['reconocimiento', 'homologacion'] as $nombre) {
            TipoTramite::query()->updateOrCreate(['nombre' => $nombre]);
        }
    }
}
