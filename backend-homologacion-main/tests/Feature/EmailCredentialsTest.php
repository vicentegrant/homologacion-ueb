<?php

namespace Tests\Feature;

use App\Models\Modalidad;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TemporaryPasswordNotification;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Coordinator\CoordinatorWorkflowTestCase;

class EmailCredentialsTest extends CoordinatorWorkflowTestCase
{
    private function admin(): User
    {
        $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->asUser($admin);

        return $admin;
    }

    public function test_user_creation_allows_mailtrap_for_existing_credential_notifications(): void
    {
        Notification::fake();
        config(['mail.default' => 'mailtrap-sdk']);
        $admin = $this->admin();
        $user = app(UserService::class)->create([
            'nombres_completos' => 'Mailtrap User',
            'tipo_identificacion' => 'cedula',
            'cedula' => '1234567890',
            'email' => 'mailtrap-user@example.test',
            'numero_celular' => '0991234567',
            'rol_id' => Role::where('nombre', 'estudiante')->firstOrFail()->id,
        ], $admin);

        $this->assertTrue($user->credentialsEmailSent);
        Notification::assertSentTo($user, TemporaryPasswordNotification::class);
    }

    public function test_mail_failure_keeps_new_user_and_returns_delivery_warning_without_secrets(): void
    {
        $this->admin();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('sensitive SMTP response'));
        Log::shouldReceive('warning')->once()->with('No se pudo enviar el correo de credenciales.', \Mockery::on(fn ($context): bool => isset($context['user_id']) && $context['exception_type'] === RuntimeException::class && count($context) === 2));
        $response = $this->postJson('/api/v1/admin/users', ['nombres_completos' => 'Usuario nuevo', 'tipo_identificacion' => 'cedula', 'cedula' => '1234567890', 'email' => 'new@example.test', 'numero_celular' => '0991234567', 'rol_id' => Role::where('nombre', 'estudiante')->firstOrFail()->id])->assertCreated()->assertJsonPath('credentials_email_sent', false)->assertJsonMissingPath('data.password');
        $user = User::findOrFail($response->json('data.id'));
        $this->assertTrue($user->password_temporal);
        $this->assertTrue($user->hasRole('estudiante'));
        $this->assertStringNotContainsString('sensitive SMTP response', $response->getContent());
        $this->assertTrue(Hash::isHashed($user->password));
    }

    public function test_creator_resends_new_credentials_revokes_sessions_and_old_reset_links(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create(['creador_id' => $admin->id]);
        $oldToken = $user->createToken('old');
        Password::createToken($user);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->postJson('/api/v1/admin/users/'.$user->id.'/credentials')->assertOk()->assertJsonPath('credentials_email_sent', true);
        Notification::assertSentTo($user, TemporaryPasswordNotification::class, function ($notification) use ($user): bool {
            $this->assertMatchesRegularExpression('/[a-zA-Z]/', $notification->temporaryPassword);
            $this->assertMatchesRegularExpression('/[0-9]/', $notification->temporaryPassword);

            return Hash::check($notification->temporaryPassword, $user->fresh()->password);
        });
        $this->assertTrue($user->fresh()->password_temporal);
        $this->assertFalse(Hash::check('password', $user->fresh()->password));
        $this->assertModelMissing($oldToken->accessToken);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_coordinator_mail_failure_keeps_student_origin_and_destination(): void
    {
        $context = $this->scenario();
        $modality = Modalidad::firstOrFail();
        $context['career']->modalidades()->attach($modality);
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP unavailable'));
        Log::shouldReceive('warning')->once();
        $response = $this->postJson('/api/v1/coordinator/students', [
            'nombres_completos' => 'Nuevo estudiante', 'tipo_identificacion' => 'cedula', 'cedula' => '1234567890',
            'email' => 'coordinator-new@example.test', 'numero_celular' => '0991234567', 'carrera_id' => $context['career']->id,
            'modalidad_id' => $modality->id, 'carrera_origen_id' => $context['otherCareer']->id, 'universidad_origen' => 'Origen',
            'tipo_institucion' => 'publica', 'periodo_cursado' => '2025',
        ])->assertCreated()->assertJsonPath('credentials_email_sent', false);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('estudiante_carreras', ['estudiante_id' => $id, 'coordinador_carrera_id' => $context['assignment']->id]);
        $this->assertDatabaseHas('antecedentes_academicos', ['estudiante_id' => $id, 'carrera_origen' => 'Industrial']);
    }

    public function test_resend_rejects_other_creators_and_inactive_accounts(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->postJson('/api/v1/admin/users/'.$user->id.'/credentials')->assertForbidden();
        $user->update(['creador_id' => $admin->id, 'cuenta_activa' => false]);
        $this->postJson('/api/v1/admin/users/'.$user->id.'/credentials')->assertStatus(409);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        Notification::assertNotSentTo($user, TemporaryPasswordNotification::class);
    }

    public function test_log_mailer_never_writes_temporary_passwords(): void
    {
        Notification::fake();
        $admin = $this->admin();
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['creador_id' => $admin->id]);
        $this->postJson('/api/v1/admin/users/'.$user->id.'/credentials')->assertOk()->assertJsonPath('credentials_email_sent', false);
        Notification::assertNotSentTo($user, TemporaryPasswordNotification::class);
    }

    public function test_password_temporal_blocks_protected_routes_and_login_reports_it(): void
    {
        $context = $this->scenario();
        $user = $context['student'];
        $user->update(['password_temporal' => true]);
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonPath('user.password_temporal', true);
        $this->asUser($user);
        $this->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/v1/student/profile')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->postJson('/api/v1/change-password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])->assertUnprocessable();
        $this->postJson('/api/v1/change-password', ['current_password' => 'password', 'password' => 'Newpass1!', 'password_confirmation' => 'Newpass1!'])->assertOk();
        $this->assertFalse($user->fresh()->password_temporal);
    }

    public function test_recovery_failure_is_generic_and_does_not_expose_transport_errors(): void
    {
        $context = $this->scenario();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP credentials'));
        Log::shouldReceive('warning')->once();
        $known = $this->postJson('/api/v1/forgot-password', ['email' => $context['student']->email])->assertOk()->json();
        $this->postJson('/api/v1/forgot-password', ['email' => 'absent@example.test'])->assertOk()->assertExactJson($known);
    }

    public function test_reset_mail_is_spanish_and_points_to_real_frontend_route(): void
    {
        $user = User::factory()->make(['nombres_completos' => 'Ana Torres', 'email' => 'ana@example.test']);
        config(['app.frontend_url' => 'http://localhost:3000']);
        $mail = (new ResetPasswordNotification('example-token'))->toMail($user);
        $html = view($mail->view, $mail->viewData)->render();
        $this->assertStringContainsString('Hola, Ana Torres', $html);
        $this->assertStringContainsString('/restablecer-contrasena?token=example-token', $html);
        $this->assertStringContainsString('email=ana%40example.test', $html);
        $this->assertStringContainsString('60 minutos', $html);
    }

    public function test_welcome_mail_contains_credentials_login_and_first_login_instructions(): void
    {
        $user = User::factory()->make(['nombres_completos' => 'Ana Torres', 'email' => 'ana@example.test']);
        config(['app.frontend_url' => 'http://localhost:3000']);
        $mail = (new TemporaryPasswordNotification('temporal123'))->toMail($user);
        $html = view($mail->view, $mail->viewData)->render();
        $this->assertStringContainsString('Ana Torres', $html);
        $this->assertStringContainsString('ana@example.test', $html);
        $this->assertStringContainsString('temporal123', $html);
        $this->assertStringContainsString('http://localhost:3000', $html);
        $this->assertStringContainsString('primer ingreso', $html);
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $context = $this->scenario();
        $user = $context['student'];
        $this->postJson('/api/v1/reset-password', ['email' => $user->email, 'token' => 'invalid', 'password' => 'Newpass1!', 'password_confirmation' => 'Newpass1!'])->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_coordinator_resends_only_to_students_created_in_own_scope(): void
    {
        Notification::fake();
        $context = $this->scenario();
        $context['student']->update(['creador_id' => $context['coordinator']->id]);
        $this->postJson('/api/v1/coordinator/students/'.$context['student']->id.'/credentials')->assertOk()->assertJsonPath('credentials_email_sent', true);
        $this->postJson('/api/v1/coordinator/students/'.$context['otherStudent']->id.'/credentials')->assertNotFound();
        $context['student']->update(['creador_id' => null]);
        $this->postJson('/api/v1/coordinator/students/'.$context['student']->id.'/credentials')->assertForbidden();
        Notification::assertSentTo($context['student'], TemporaryPasswordNotification::class);
    }

    public function test_password_flag_migration_roundtrip_preserves_existing_state(): void
    {
        $context = $this->scenario();
        $user = $context['student'];
        $user->update(['password_temporal' => true]);
        $migration = require database_path('migrations/2026_10_04_154632_add_password_temporal_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'password_temporal'));
        $this->assertTrue(DB::table('users')->where('id', $user->id)->value('must_change_password'));
        $migration->up();
        $this->assertTrue($user->fresh()->password_temporal);

    }
}
