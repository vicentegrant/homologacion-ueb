<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('password_temporal')->default(false);
        });
        DB::table('users')->update(['password_temporal' => DB::raw('must_change_password')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->update(['must_change_password' => DB::raw('password_temporal')]);
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_temporal');
        });
    }
};
