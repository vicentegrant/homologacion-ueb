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
        Schema::create('resultados_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('coordinador_id')->constrained('users');
            $table->enum('conclusion_general', ['total', 'parcial', 'rechazada']);
            $table->unsignedInteger('total_creditos_reconocidos')->default(0);
            $table->timestamps();
        });

        Schema::create('resoluciones_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('coordinador_id')->constrained('users');
            $table->string('numero_resolucion', 100)->unique();
            $table->date('fecha_aprobacion');
            $table->string('ruta_archivo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resoluciones_solicitud');
        Schema::dropIfExists('resultados_solicitud');
    }
};
