<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Database\Seeders\StudentDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

class StudentDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_data_is_idempotent_and_links_existing_test_student_to_a_career(): void
    {
        $this->seed(StudentDemoSeeder::class);
        $coordinator = User::query()->where('email', 'coordinador-demo@example.com')->firstOrFail();
        $password = $coordinator->password;

        $this->seed(StudentDemoSeeder::class);

        $this->assertDatabaseCount('carreras', 1);
        $this->assertDatabaseCount('coordinador_carreras', 1);
        $this->assertDatabaseCount('estudiante_carreras', 1);
        $this->assertDatabaseCount('documentos_requeridos_proceso', 6);
        $this->assertSame($password, $coordinator->fresh()->password);
        $student = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->withToken($student->createToken('activation')->plainTextToken)->getJson('/api/v1/student/catalogo')->assertForbidden();
        $token = $this->postJson('/api/v1/change-password', ['current_password' => $student->cedula, 'password' => 'DemoUeb2026!', 'password_confirmation' => 'DemoUeb2026!'])->assertOk()->json('token');
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/student/catalogo')
            ->assertOk()->assertJsonCount(1, 'data.asignaciones')->assertJsonPath('data.asignaciones.0.disponible', true);
    }

    public function test_demo_seeder_refuses_production_without_writing_data(): void
    {
        $this->app->instance('env', 'production');

        try {
            (new StudentDemoSeeder)->run();
            $this->fail('No debe crear datos de demostración en producción.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('solo se permiten', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('carreras', 0);
    }
}
