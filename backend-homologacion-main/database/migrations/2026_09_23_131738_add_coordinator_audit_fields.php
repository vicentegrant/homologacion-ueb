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
        Schema::table('historial_estados_solicitud', function (Blueprint $table) {
            $table->foreignId('usuario_responsable_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('resultados_solicitud', function (Blueprint $table) {
            $table->string('ruta_informe_tecnico')->nullable();
            $table->timestamp('informe_generado_at')->nullable();
        });

        Schema::table('verificaciones_documento', function (Blueprint $table) {
            $table->unique(['solicitud_documento_id', 'coordinador_id'], 'verificacion_documento_coordinador_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verificaciones_documento', function (Blueprint $table) {
            $table->dropUnique('verificacion_documento_coordinador_unique');
        });

        Schema::table('resultados_solicitud', function (Blueprint $table) {
            $table->dropColumn(['ruta_informe_tecnico', 'informe_generado_at']);
        });

        Schema::table('historial_estados_solicitud', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_responsable_id');
        });
    }
};
