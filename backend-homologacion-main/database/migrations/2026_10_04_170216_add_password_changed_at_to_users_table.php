<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registrar la fecha del cambio sin modificar contraseñas existentes.
     */
    public function up(): void
    {
        // Las cuentas existentes conservan su contraseña y su estado de activación.
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_changed_at')->nullable();
        });
    }

    /**
     * Retirar únicamente la columna de fecha añadida por esta migración.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_changed_at');
        });
    }
};
