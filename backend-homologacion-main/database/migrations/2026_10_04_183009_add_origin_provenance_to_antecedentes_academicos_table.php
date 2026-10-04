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
        Schema::table('antecedentes_academicos', function (Blueprint $table) {
            // Los registros históricos quedan sin clasificar hasta que el coordinador los revise.
            $table->string('procedencia', 20)->nullable();
            $table->foreignId('carrera_origen_id')->nullable()->constrained('carreras')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('antecedentes_academicos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrera_origen_id');
            $table->dropColumn('procedencia');
        });
    }
};
