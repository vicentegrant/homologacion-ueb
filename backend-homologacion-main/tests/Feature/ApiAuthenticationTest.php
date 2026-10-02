<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_public_registration_rejects_valid_payload_without_creating_a_user_or_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'nombres_completos' => 'Ana Pérez',
            'cedula' => '0912345678',
            'email' => 'ana@example.com',
            'numero_celular' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Administrador',
        ]);

        $response->assertForbidden()->assertJsonPath('success', false);
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_me_and_logout_revoke_only_current_token(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $user->assignRole('Coordinador');
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        $token = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.roles.0', 'coordinador');
        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();
        $this->assertSame(1, PersonalAccessToken::count());
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_administrator_route_rejects_guests_and_other_roles(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();

        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $this->withToken($student->createToken('test')->plainTextToken)
            ->getJson('/api/v1/roles')->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        Auth::forgetGuards();
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/roles')->assertOk()->assertJsonFragment(['administrador'])
            ->assertJsonFragment(['id' => $admin->roles()->firstOrFail()->id, 'nombre' => 'administrador']);
    }

    public function test_public_registration_does_not_validate_or_accept_incomplete_input(): void
    {
        $this->postJson('/api/v1/register', [
            'nombres_completos' => 'Ana',
            'email' => 'invalid',
            'password' => 'short',
        ])->assertForbidden()->assertJsonPath('message', 'El registro público no está habilitado. Solicite su cuenta al Administrador.');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_returns_403_when_account_is_inactive(): void
    {
        $user = User::factory()->create([
            'cuenta_activa' => false,
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertForbidden()->assertExactJson([
            'success' => false,
            'message' => 'La cuenta se encuentra inactiva.',
        ]);
    }

    public function test_inactive_user_cannot_use_an_existing_token(): void
    {
        $user = User::factory()->create(['cuenta_activa' => false]);
        $token = $user->createToken('existing')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('success', false);
        $this->assertSame(0, $user->tokens()->count());
    }
}
