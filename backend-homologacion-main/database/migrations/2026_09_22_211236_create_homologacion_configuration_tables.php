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
        Schema::create('tramite_proceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite');
            $table->foreignId('tipo_proceso_id')->constrained('tipos_proceso');
            $table->timestamps();

            $table->unique(['tipo_tramite_id', 'tipo_proceso_id']);
        });

        Schema::create('documentos_requeridos_proceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_proceso_id')->constrained('tramite_proceso')->cascadeOnDelete();
            $table->foreignId('carrera_id')->nullable()->constrained('carreras')->nullOnDelete();
            $table->string('nombre_documento');
            $table->text('descripcion')->nullable();
            $table->string('ruta_ejemplo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_requeridos_proceso');
        Schema::dropIfExists('tramite_proceso');
    }
};
