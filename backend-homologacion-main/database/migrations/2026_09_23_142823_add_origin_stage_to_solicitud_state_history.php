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
            $table->string('etapa_origen')->nullable()->after('usuario_responsable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historial_estados_solicitud', function (Blueprint $table) {
            $table->dropColumn('etapa_origen');
        });
    }
};
