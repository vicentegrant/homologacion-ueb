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
        Schema::create('mallas_curriculares', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->enum('tipo', ['institucional', 'origen']);
            $table->foreignId('carrera_id')->nullable()->constrained('carreras')->nullOnDelete();
            $table->foreignId('creador_id')->constrained('users');
            $table->foreignId('estudiante_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('asignaturas_creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('malla_curricular_id')->constrained('mallas_curriculares')->cascadeOnDelete();
            $table->string('codigo_asignatura', 50);
            $table->string('nombre_asignatura', 150);
            $table->unsignedInteger('numero_creditos');
            $table->enum('nivel_ciclo', ['primero', 'segundo', 'tercero', 'cuarto', 'quinto', 'sexto', 'septimo', 'octavo', 'noveno', 'decimo']);
            $table->unsignedInteger('hr_carga_horaria');
            $table->timestamps();

            $table->unique(['malla_curricular_id', 'codigo_asignatura']);
        });

        Schema::create('temas_silabo_asignaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained('asignaturas_creditos')->cascadeOnDelete();
            $table->string('tema');
            $table->string('unidad_analitica');
            $table->timestamps();
        });

        Schema::create('comparaciones_asignatura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('asignatura_origen_id')->constrained('asignaturas_creditos');
            $table->foreignId('asignatura_destino_id')->constrained('asignaturas_creditos');
            $table->decimal('porcentaje_coincidencia', 5, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['solicitud_id', 'asignatura_origen_id', 'asignatura_destino_id'], 'comparacion_asignaturas_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comparaciones_asignatura');
        Schema::dropIfExists('temas_silabo_asignaturas');
        Schema::dropIfExists('asignaturas_creditos');
        Schema::dropIfExists('mallas_curriculares');
    }
};
