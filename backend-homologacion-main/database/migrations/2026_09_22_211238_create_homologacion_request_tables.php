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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users');
            $table->foreignId('coordinador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tramite_proceso_id')->constrained('tramite_proceso');
            $table->string('procedencia_estudios');
            $table->timestamps();
        });

        Schema::create('historial_estados_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('estado_solicitud_id')->constrained('estados_solicitud');
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        Schema::create('oficios_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->string('numero_oficio', 100);
            $table->date('fecha_oficio');
            $table->string('ruta_oficio');
            $table->timestamps();
        });

        Schema::create('solicitud_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('documento_requerido_proceso_id')->constrained('documentos_requeridos_proceso');
            $table->foreignId('estado_documento_id')->constrained('estados_documento');
            $table->string('ruta_documento_oficio')->nullable();
            $table->boolean('validez')->default(false);
            $table->timestamps();

            $table->unique(['solicitud_id', 'documento_requerido_proceso_id']);
        });

        Schema::create('observaciones_documentacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_documento_id')->constrained('solicitud_documentos')->cascadeOnDelete();
            $table->text('observacion');
            $table->timestamps();
        });

        Schema::create('verificaciones_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_documento_id')->constrained('solicitud_documentos')->cascadeOnDelete();
            $table->foreignId('coordinador_id')->constrained('users');
            $table->boolean('estado');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verificaciones_documento');
        Schema::dropIfExists('observaciones_documentacion');
        Schema::dropIfExists('solicitud_documentos');
        Schema::dropIfExists('oficios_solicitud');
        Schema::dropIfExists('historial_estados_solicitud');
        Schema::dropIfExists('solicitudes');
    }
};
