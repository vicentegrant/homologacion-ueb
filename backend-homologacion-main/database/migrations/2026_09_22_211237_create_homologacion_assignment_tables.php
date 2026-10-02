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
        Schema::create('antecedentes_academicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->string('universidad_origen');
            $table->string('carrera_origen');
            $table->string('tipo_institucion', 100);
            $table->string('periodo_cursado', 100);
            $table->timestamps();
        });

        Schema::create('coordinador_carreras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinador_id')->constrained('users');
            $table->foreignId('carrera_id')->constrained('carreras');
            $table->timestamps();

            $table->unique(['coordinador_id', 'carrera_id']);
        });

        Schema::create('estudiante_carreras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coordinador_carrera_id')->constrained('coordinador_carreras')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['estudiante_id', 'coordinador_carrera_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudiante_carreras');
        Schema::dropIfExists('coordinador_carreras');
        Schema::dropIfExists('antecedentes_academicos');
    }
};
