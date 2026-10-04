<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\DevelopmentAuthSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ForcedPasswordFlowTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [
            'administrador' => ['administrador', '0100000009', '/api/v1/admin/dashboard'],
            'coordinador' => ['coordinador', '0200000008', '/api/v1/coordinator/students'],
            'estudiante' => ['estudiante', '0300000007', '/api/v1/student/profile'],
        ];
    }

    #[DataProvider('roles')]
    public function test_every_role_completes_forced_change_and_cannot_access_other_roles(string $role, string $identification, string $ownRoute): void
    {
        $this->seed(DevelopmentAuthSeeder::class);
        $user = User::where('email', $role.'.auth@example.test')->firstOrFail();
        $otherAccess = $user->createToken('older-device');
        $resetToken = Password::createToken($user);
        $this->travelTo(now()->startOfSecond());
        $initial = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => $identification])
            ->assertOk()->assertJsonPath('user.must_change_password', true)->assertJsonMissingPath('user.password')->json('token');
        $this->assertTrue(Hash::check($identification, $user->password));
        $this->assertNotSame($identification, $user->password);
        $routes = ['/api/v1/me', '/api/v1/admin/dashboard', '/api/v1/coordinator/students', '/api/v1/student/profile'];
        foreach ($routes as $route) {
            $this->withToken($initial)->getJson($route)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        }
        $changed = $this->postJson('/api/v1/change-password', [
            'current_password' => $identification, 'password' => 'NuevaUeb2026!', 'password_confirmation' => 'NuevaUeb2026!',
        ])->assertOk()->json('token');
        $this->assertNotSame($initial, $changed);
        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertFalse($user->password_temporal);
        $this->assertTrue($user->password_changed_at->equalTo(now()));
        $this->assertTrue(Hash::check('NuevaUeb2026!', $user->password));
        $this->assertModelMissing($otherAccess->accessToken);
        $this->assertFalse(Password::tokenExists($user, $resetToken));
        Auth::forgetGuards();
        $this->withToken($initial)->getJson('/api/v1/me')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($changed)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->getJson($ownRoute)->assertOk();
        foreach (array_diff($routes, ['/api/v1/me', $ownRoute]) as $route) {
            $this->getJson($route)->assertForbidden();
        }
        $this->postJson('/api/v1/logout')->assertOk();
    }

    public static function invalidPasswords(): array
    {
        return [
            'igual a cedula' => ['0300000007', '0300000007'],
            'corta' => ['Ab1!', 'Ab1!'],
            'sin mayuscula' => ['abcdef1!', 'abcdef1!'],
            'sin minuscula' => ['ABCDEF1!', 'ABCDEF1!'],
            'sin numero' => ['Abcdefg!', 'Abcdefg!'],
            'sin simbolo' => ['Abcdef12', 'Abcdef12'],
            'confirmacion distinta' => ['NuevaUeb2026!', 'OtraUeb2026!'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_change_preserves_password_flag_and_access(string $password, string $confirmation): void
    {
        $this->seed(DevelopmentAuthSeeder::class);
        $user = User::where('email', 'estudiante.auth@example.test')->firstOrFail();
        $access = $user->createToken('test');
        $this->withToken($access->plainTextToken)->postJson('/api/v1/change-password', [
            'current_password' => $user->cedula, 'password' => $password, 'password_confirmation' => $confirmation,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check($user->cedula, $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->password_changed_at);
        $this->assertModelExists($access->accessToken);
    }

    public function test_current_password_cannot_be_reused_after_activation(): void
    {
        $user = User::factory()->create(['password' => 'NuevaUeb2026!']);
        $this->withToken($user->createToken('test')->plainTextToken)->postJson('/api/v1/change-password', [
            'current_password' => 'NuevaUeb2026!', 'password' => 'NuevaUeb2026!', 'password_confirmation' => 'NuevaUeb2026!',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_logout_is_allowed_before_changing_password(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();
        $this->withToken($user->createToken('test')->plainTextToken)->postJson('/api/v1/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_each_successful_login_issues_a_different_token(): void
    {
        $user = User::factory()->create(['password' => 'NuevaUeb2026!']);
        $payload = ['email' => $user->email, 'password' => 'NuevaUeb2026!'];
        $first = $this->postJson('/api/v1/login', $payload)->assertOk()->json('token');
        $second = $this->postJson('/api/v1/login', $payload)->assertOk()->json('token');
        $this->assertNotSame($first, $second);
    }

    public function test_recovery_cannot_reuse_the_current_password_and_preserves_reset_token(): void
    {
        $user = User::factory()->create(['password' => 'NuevaUeb2026!']);
        $token = Password::createToken($user);
        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'NuevaUeb2026!', 'password_confirmation' => 'NuevaUeb2026!',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Password::tokenExists($user, $token));
        $this->assertTrue(Hash::check('NuevaUeb2026!', $user->fresh()->password));
        $this->assertNull($user->fresh()->password_changed_at);
    }

    #[DataProvider('roles')]
    public function test_creation_uses_valid_identification_for_each_role(string $role, string $identification, string $ownRoute): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $creator = User::factory()->create();
        $user = app(UserService::class)->create([
            'nombres_completos' => 'Cuenta nueva', 'cedula' => $identification, 'tipo_identificacion' => 'cedula',
            'email' => $role.'-new@example.test', 'numero_celular' => '0990000000',
            'rol_id' => Role::where('nombre', $role)->firstOrFail()->id,
            'password' => 'UnValorQueDebeIgnorarse!',
        ], $creator);
        $this->assertTrue(Hash::check($identification, $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->password_changed_at);
        $this->assertTrue($user->hasRole($role));
    }

    public function test_service_rejects_invalid_identification_before_creating_an_account(): void
    {
        $creator = User::factory()->create();
        $count = User::count();
        try {
            app(UserService::class)->create(['cedula' => '123ABC', 'tipo_identificacion' => 'cedula'], $creator);
            $this->fail('La identificación inválida debía rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cedula', $exception->errors());
        }
        $this->assertSame($count, User::count());
    }

    public function test_reset_demo_restores_only_reserved_accounts_and_revokes_access(): void
    {
        $untouched = User::factory()->create(['password' => 'Conservar2026!']);
        $this->artisan('auth:reset-demo')->assertSuccessful();
        $user = User::where('email', 'estudiante.auth@example.test')->firstOrFail();
        $user->forceFill(['password' => 'NuevaUeb2026!', 'must_change_password' => false, 'password_changed_at' => now()])->save();
        $access = $user->createToken('old');
        $resetToken = Password::createToken($user);
        DB::table('sessions')->insert(['id' => 'auth-demo-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->artisan('auth:reset-demo')->assertSuccessful();
        $this->assertSame(4, User::count());
        $this->assertTrue(Hash::check('0300000007', $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->password_changed_at);
        $this->assertModelMissing($access->accessToken);
        $this->assertFalse(Password::tokenExists($user, $resetToken));
        $this->assertDatabaseMissing('sessions', ['id' => 'auth-demo-session']);
        $this->assertTrue(Hash::check('Conservar2026!', $untouched->fresh()->password));
    }

    public function test_demo_command_and_seeder_refuse_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->artisan('auth:reset-demo')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->expectException(RuntimeException::class);
        app(DevelopmentAuthSeeder::class)->run();
    }

    public function test_login_errors_are_generic_and_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'Conservar2026!', 'cuenta_activa' => false]);
        $failure = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'Conservar2026!'])->assertUnauthorized()->json();
        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->postJson('/api/v1/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnauthorized()->assertExactJson($failure);
        }
        $this->postJson('/api/v1/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_timestamp_migration_is_reversible_and_preserves_existing_password(): void
    {
        $user = User::factory()->create(['password' => 'Conservar2026!']);
        $migration = require database_path('migrations/2026_10_04_170216_add_password_changed_at_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'password_changed_at'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'password_changed_at'));
        $this->assertTrue(Hash::check('Conservar2026!', $user->fresh()->password));
        $this->assertNull($user->fresh()->password_changed_at);
    }
}
