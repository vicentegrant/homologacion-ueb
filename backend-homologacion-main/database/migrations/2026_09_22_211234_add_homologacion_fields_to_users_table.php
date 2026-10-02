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
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'nombres_completos');
            $table->string('cedula', 20)->nullable()->unique();
            $table->string('numero_celular', 20)->nullable();
            $table->boolean('cuenta_activa')->default(true);
            $table->foreignId('creador_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creador_id');
            $table->dropColumn(['cedula', 'numero_celular', 'cuenta_activa']);
            $table->renameColumn('nombres_completos', 'name');
        });
    }
};
