<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostgreSqlIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_real_postgresql_and_custom_role_schema(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('backend_homologacion_test', DB::connection()->getDatabaseName());
        $this->assertTrue(Schema::hasColumns('roles', ['id', 'nombre']));
        $this->assertFalse(Schema::hasColumn('roles', 'name'));
        $this->assertTrue(Schema::hasTable('user_has_rol'));
        $this->assertFalse(Schema::hasTable('model_has_roles'));
    }

    public function test_unique_pivot_constraint_prevents_duplicate_role_assignments(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $role = Role::query()->where('nombre', 'estudiante')->firstOrFail();
        $user->assignRole($role);
        $user->assignRole($role);

        $this->assertSame(1, DB::table('user_has_rol')->where('user_id', $user->id)->where('rol_id', $role->id)->count());

        try {
            DB::transaction(fn () => DB::table('user_has_rol')->insert([
                'user_id' => $user->id,
                'rol_id' => $role->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->fail('PostgreSQL debe rechazar una asignación de rol duplicada.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        }
    }

    public function test_cors_allows_configured_react_origin_without_wildcard(): void
    {
        $origin = config('app.frontend_url');

        $this->call('OPTIONS', '/api/v1/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', $origin);

        $this->assertNotContains('*', config('cors.allowed_origins'));
    }
}
