<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facultades', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre', 150)->unique();
            $t->boolean('activa')->default(true);
            $t->timestamps();
        });
        Schema::create('modalidades', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre', 150)->unique();
            $t->boolean('activa')->default(true);
            $t->timestamps();
        });
        foreach (['Presencial', 'Híbrida', 'En línea'] as $nombre) {
            DB::table('modalidades')->insert(['nombre' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('carreras', function (Blueprint $t): void {
            $t->foreignId('facultad_id')->nullable()->constrained('facultades')->restrictOnDelete();
            $t->boolean('activa')->default(true);
        });
        Schema::create('carrera_modalidad', function (Blueprint $t): void {
            $t->foreignId('carrera_id')->constrained('carreras')->cascadeOnDelete();
            $t->foreignId('modalidad_id')->constrained('modalidades')->restrictOnDelete();
            $t->primary(['carrera_id', 'modalidad_id']);
        });
        Schema::table('users', function (Blueprint $t): void {
            $t->string('tipo_identificacion', 15)->default('cedula');
        });
        Schema::table('estudiante_carreras', function (Blueprint $t): void {
            $t->foreignId('modalidad_id')->nullable()->constrained('modalidades')->restrictOnDelete();
        });
        Schema::table('documentos_requeridos_proceso', function (Blueprint $t): void {
            $t->boolean('activo')->default(true);
            $t->boolean('obligatorio')->default(true);
        });
        Schema::table('solicitud_documentos', function (Blueprint $t): void {
            $t->timestamp('recibido_at')->nullable();
            $t->foreignId('recibido_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('revisado_at')->nullable();
            $t->foreignId('revisado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->boolean('obligatorio')->default(true);
            $t->string('requisito_nombre')->nullable();
            $t->text('requisito_descripcion')->nullable();
        });
        Schema::create('historial_documentos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('solicitud_documento_id')->constrained('solicitud_documentos')->cascadeOnDelete();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->string('estado', 30);
            $t->text('observacion')->nullable();
            $t->timestamps();
        });
        DB::table('solicitud_documentos')->orderBy('id')->chunkById(200, function ($documents): void {
            foreach ($documents as $d) {
                $r = DB::table('documentos_requeridos_proceso')->find($d->documento_requerido_proceso_id);
                DB::table('solicitud_documentos')->where('id', $d->id)->update(['requisito_nombre' => $r?->nombre_documento, 'requisito_descripcion' => $r?->descripcion]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_documentos');
        Schema::table('solicitud_documentos', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('recibido_por_id');
            $t->dropConstrainedForeignId('revisado_por_id');
            $t->dropColumn(['recibido_at', 'revisado_at', 'obligatorio', 'requisito_nombre', 'requisito_descripcion']);
        });
        Schema::table('documentos_requeridos_proceso', function (Blueprint $t): void {
            $t->dropColumn(['activo', 'obligatorio']);
        });
        Schema::table('estudiante_carreras', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('modalidad_id');
        });
        Schema::table('users', function (Blueprint $t): void {
            $t->dropColumn('tipo_identificacion');
        });
        Schema::dropIfExists('carrera_modalidad');
        Schema::table('carreras', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('facultad_id');
            $t->dropColumn('activa');
        });
        Schema::dropIfExists('modalidades');
        Schema::dropIfExists('facultades');
    }
};
