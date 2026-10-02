<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_routes_require_authentication_and_administrator_role(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();

        foreach (['Estudiante', 'Coordinador'] as $role) {
            $this->authenticateAs($role);
            $this->getJson('/api/v1/admin/users')->assertForbidden()->assertExactJson([
                'success' => false,
                'message' => 'No tiene permisos para realizar esta acción.',
            ]);
        }
    }

    public function test_administrator_creates_a_user_atomically_with_creator_role_and_hashed_password(): void
    {
        $admin = $this->authenticateAs('Administrador');
        $role = Role::query()->where('nombre', 'coordinador')->firstOrFail();

        $this->postJson('/api/v1/admin/users', $this->validPayload(['rol_id' => $role->id]))
            ->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.roles.0', 'coordinador');

        $created = User::query()->where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertSame($admin->id, $created->creador_id);
        $this->assertTrue($created->cuenta_activa);
        $this->assertTrue($created->hasRole('Coordinador'));
        $this->assertTrue(Hash::check('Password123!', $created->password));
        $this->assertNotSame('Password123!', $created->password);
    }

    public function test_user_creation_rejects_duplicate_identity_fields_and_unknown_role(): void
    {
        $this->authenticateAs('Administrador');
        $existing = User::factory()->create(['cedula' => '0911111111', 'email' => 'existing@example.com']);
        $role = Role::query()->where('nombre', 'estudiante')->firstOrFail();

        $this->postJson('/api/v1/admin/users', $this->validPayload(['cedula' => $existing->cedula, 'rol_id' => $role->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('cedula');
        $this->postJson('/api/v1/admin/users', $this->validPayload(['email' => $existing->email, 'rol_id' => $role->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/admin/users', $this->validPayload(['rol_id' => 999999]))
            ->assertUnprocessable()->assertJsonValidationErrors('rol_id');
    }

    public function test_admin_can_search_filter_sort_and_paginate_users(): void
    {
        $this->authenticateAs('Administrador');
        $student = User::factory()->create(['nombres_completos' => 'Persona Buscable', 'cuenta_activa' => false]);
        $student->assignRole('Estudiante');

        $this->getJson('/api/v1/admin/users?search=Buscable&rol=estudiante&cuenta_activa=0&per_page=1')
            ->assertOk()->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 1);
    }

    public function test_admin_updates_allowed_fields_and_only_changes_password_when_provided(): void
    {
        $this->authenticateAs('Administrador');
        $user = User::factory()->create(['password' => 'OldPassword123!']);
        $user->assignRole('Estudiante');
        $originalPassword = $user->password;

        $this->patchJson("/api/v1/admin/users/{$user->id}", ['nombres_completos' => 'Nombre actualizado'])
            ->assertOk()->assertJsonPath('data.nombres_completos', 'Nombre actualizado');
        $this->assertSame($originalPassword, $user->refresh()->password);

        $coordinatorRole = Role::query()->where('nombre', 'coordinador')->firstOrFail();
        $this->patchJson("/api/v1/admin/users/{$user->id}", [
            'password' => 'NewPassword123!',
            'rol_id' => $coordinatorRole->id,
        ])->assertOk()->assertJsonPath('data.roles.0', 'coordinador');
        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
    }

    public function test_admin_can_deactivate_and_reactivate_a_user_and_deactivation_revokes_tokens(): void
    {
        $this->authenticateAs('Administrador');
        $user = User::factory()->create();
        $user->assignRole('Estudiante');
        $user->createToken('existing');

        $this->patchJson("/api/v1/admin/users/{$user->id}/status", ['cuenta_activa' => false])
            ->assertOk()->assertJsonPath('data.cuenta_activa', false);
        $this->assertFalse($user->refresh()->cuenta_activa);
        $this->assertSame(0, $user->tokens()->count());

        $this->patchJson("/api/v1/admin/users/{$user->id}/status", ['cuenta_activa' => true])
            ->assertOk()->assertJsonPath('data.cuenta_activa', true);
    }

    public function test_administrator_cannot_deactivate_or_remove_own_administrator_role(): void
    {
        $admin = $this->authenticateAs('Administrador');

        $this->patchJson("/api/v1/admin/users/{$admin->id}/status", ['cuenta_activa' => false])->assertStatus(409);

        $studentRole = Role::query()->where('nombre', 'estudiante')->firstOrFail();
        $this->patchJson("/api/v1/admin/users/{$admin->id}", ['rol_id' => $studentRole->id])->assertStatus(409);
        $this->assertTrue($admin->refresh()->hasRole('Administrador'));
    }

    public function test_unknown_role_filter_returns_422(): void
    {
        $this->authenticateAs('Administrador');

        $this->getJson('/api/v1/admin/users?rol=Inexistente')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['rol' => 'El rol seleccionado no existe.']);
    }

    public function test_role_filter_uses_the_custom_role_source_of_truth(): void
    {
        $this->authenticateAs('Administrador');
        Role::query()->create(['nombre' => 'externo']);

        $this->getJson('/api/v1/admin/users?rol=externo')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    private function authenticateAs(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->withToken($user->createToken('test')->plainTextToken);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nombres_completos' => 'Usuario Nuevo',
            'cedula' => '0922222222',
            'email' => 'nuevo@example.com',
            'numero_celular' => '0992222222',
            'password' => 'Password123!',
        ], $overrides);
    }
}
