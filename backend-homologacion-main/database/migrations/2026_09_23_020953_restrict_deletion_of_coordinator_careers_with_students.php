<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('estudiante_carreras', function (Blueprint $table): void {
            $table->dropForeign(['coordinador_carrera_id']);
            $table->foreign('coordinador_carrera_id')->references('id')->on('coordinador_carreras')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudiante_carreras', function (Blueprint $table): void {
            $table->dropForeign(['coordinador_carrera_id']);
            $table->foreign('coordinador_carrera_id')->references('id')->on('coordinador_carreras')->cascadeOnDelete();
        });
    }
};
