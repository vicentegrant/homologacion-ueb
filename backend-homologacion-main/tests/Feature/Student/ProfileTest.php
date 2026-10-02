<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_can_read_own_profile_without_credentials(): void
    {
        $student = $this->authenticateStudent();

        $this->getJson('/api/v1/student/profile')->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.email', $student->email)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_student_can_update_profile_without_changing_protected_fields(): void
    {
        $student = $this->authenticateStudent();
        $other = User::factory()->create();
        $originalPassword = $student->password;
        $originalEmailVerifiedAt = $student->email_verified_at;
        $originalName = $student->nombres_completos;
        $originalEmail = $student->email;
        $originalCedula = $student->cedula;

        $this->patchJson('/api/v1/student/profile', [
            'nombres_completos' => 'Nombre actualizado',
            'email' => 'nuevo@example.com',
            'numero_celular' => '0991111111',
            'id' => $other->id,
            'cuenta_activa' => false,
            'rol_id' => 1,
            'roles' => ['administrador'],
            'creador_id' => $other->id,
            'password' => 'ChangedPassword123!',
            'email_verified_at' => now()->toISOString(),
        ])->assertOk()->assertJsonPath('data.nombres_completos', $originalName);

        $student->refresh();
        $this->assertSame($originalName, $student->nombres_completos);
        $this->assertSame($originalEmail, $student->email);
        $this->assertSame($originalCedula, $student->cedula);
        $this->assertSame('0991111111', $student->numero_celular);
        $this->assertEquals($originalEmailVerifiedAt, $student->email_verified_at);
        $this->assertNull($student->creador_id);
        $this->assertTrue($student->cuenta_activa);
        $this->assertSame($originalPassword, $student->password);
        $this->assertSame(['estudiante'], $student->getRoleNames()->all());
        $this->assertSame($other->nombres_completos, $other->fresh()->nombres_completos);
    }

    public function test_student_cannot_change_identity_fields(): void
    {
        $student = $this->authenticateStudent();

        $this->patchJson('/api/v1/student/profile', [
            'nombres_completos' => 'Nombre no permitido',
            'email' => 'correo-no-permitido@example.com',
            'cedula' => '0999999999',
        ])
            ->assertOk();

        $student->refresh();
        $this->assertNotSame('Nombre no permitido', $student->nombres_completos);
        $this->assertNotSame('correo-no-permitido@example.com', $student->email);
        $this->assertNotSame('0999999999', $student->cedula);
        $this->assertNotNull($student->email_verified_at);
    }

    public function test_identity_fields_are_ignored_without_changing_profile(): void
    {
        $student = $this->authenticateStudent();
        $other = User::factory()->create();

        $this->patchJson('/api/v1/student/profile', ['email' => $other->email, 'cedula' => $other->cedula])
            ->assertOk();

        $this->assertSame($student->email, $student->fresh()->email);
        $this->assertSame($student->cedula, $student->fresh()->cedula);
    }

    public function test_invalid_profile_fields_return_422(): void
    {
        $this->authenticateStudent();

        $this->patchJson('/api/v1/student/profile', [
            'nombres_completos' => '', 'cedula' => '1', 'email' => 'invalid', 'numero_celular' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['numero_celular']);
    }

    public function test_profile_requires_authentication_and_student_role(): void
    {
        $this->getJson('/api/v1/student/profile')->assertUnauthorized();
        $this->patchJson('/api/v1/student/profile', [])->assertUnauthorized();
        $this->seed(RoleSeeder::class);

        foreach (['Administrador', 'Coordinador'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            Auth::forgetGuards();
            $this->withToken($user->createToken('test')->plainTextToken);
            $this->getJson('/api/v1/student/profile')->assertForbidden();
            $this->patchJson('/api/v1/student/profile', ['nombres_completos' => 'No permitido'])->assertForbidden();
            $this->assertSame($user->nombres_completos, $user->fresh()->nombres_completos);
        }
    }

    public function test_inactive_student_cannot_update_profile(): void
    {
        $student = $this->authenticateStudent();
        $student->update(['cuenta_activa' => false]);

        $this->patchJson('/api/v1/student/profile', ['nombres_completos' => 'No permitido'])->assertForbidden();

        $this->assertSame($student->nombres_completos, $student->fresh()->nombres_completos);
    }

    private function authenticateStudent(): User
    {
        $this->seed(RoleSeeder::class);
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $this->withToken($student->createToken('test')->plainTextToken);

        return $student;
    }
}
