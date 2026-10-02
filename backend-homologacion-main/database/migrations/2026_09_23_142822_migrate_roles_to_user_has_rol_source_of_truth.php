<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_has_rol', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'rol_id']);
        });

        DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->orderBy('model_id')
            ->each(function (object $assignment): void {
                DB::table('user_has_rol')->insertOrIgnore([
                    'user_id' => $assignment->model_id,
                    'rol_id' => $assignment->role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('nombre')->nullable();
        });

        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update(['nombre' => Str::lower($role->name)]);
        });

        Schema::drop('model_has_roles');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->dropColumn(['name', 'guard_name']);
            $table->string('nombre')->nullable(false)->change();
            $table->unique('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('guard_name')->default('web');
        });

        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update(['name' => Str::title($role->nombre)]);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        DB::table('user_has_rol')->orderBy('user_id')->each(function (object $assignment): void {
            DB::table('model_has_roles')->insert([
                'role_id' => $assignment->rol_id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $assignment->user_id,
            ]);
        });

        Schema::drop('user_has_rol');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
            $table->dropColumn('nombre');
            $table->string('name')->nullable(false)->change();
            $table->unique(['name', 'guard_name']);
        });
    }
};
