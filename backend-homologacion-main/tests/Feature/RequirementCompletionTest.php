<?php

namespace Tests\Feature;

use App\Models\Modalidad;
use App\Models\User;
use App\Notifications\ResetPasswordNotification as ResetPassword;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Coordinator\CoordinatorWorkflowTestCase;

class RequirementCompletionTest extends CoordinatorWorkflowTestCase
{
    public static function academicBackgrounds(): array
    {
        return [
            'universidad publica' => ['publica', '2025'],
            'universidad privada' => ['privada', '2024-2025'],
            'instituto' => ['instituto', '2024-2025'],
            'primer ciclo' => ['publica', 'Primer ciclo / semestre'],
            'segundo ciclo' => ['publica', 'Segundo ciclo / semestre'],
            'tercer ciclo' => ['publica', 'Tercer ciclo / semestre'],
            'cuarto ciclo' => ['publica', 'Cuarto ciclo / semestre'],
            'quinto ciclo' => ['publica', 'Quinto ciclo / semestre'],
            'sexto ciclo' => ['publica', 'Sexto ciclo / semestre'],
            'séptimo ciclo' => ['publica', 'Séptimo ciclo / semestre'],
            'octavo ciclo' => ['publica', 'Octavo ciclo / semestre'],
            'noveno ciclo' => ['publica', 'Noveno ciclo / semestre'],
            'décimo ciclo' => ['publica', 'Décimo ciclo / semestre'],
        ];
    }

    #[DataProvider('academicBackgrounds')]
    public function test_student_saves_supported_institution_and_period(string $type, string $period): void
    {
        $context = $this->scenario();
        $this->asUser($context['student']);
        $this->postJson('/api/v1/student/antecedentes', [
            'universidad_origen' => 'Institución de origen', 'carrera_origen' => 'Sistemas',
            'tipo_institucion' => $type, 'periodo_cursado' => $period,
        ])->assertCreated()->assertJsonPath('data.tipo_institucion', $type);
        $this->assertDatabaseHas('antecedentes_academicos', [
            'estudiante_id' => $context['student']->id, 'tipo_institucion' => $type, 'periodo_cursado' => $period,
        ]);
    }

    public function test_student_rejects_invalid_institution_and_period_on_create_and_update(): void
    {
        $context = $this->scenario();
        $this->asUser($context['student']);
        $payload = ['universidad_origen' => 'Origen', 'carrera_origen' => 'Sistemas', 'tipo_institucion' => 'extranjera', 'periodo_cursado' => '2024/2025'];
        $this->postJson('/api/v1/student/antecedentes', $payload)->assertUnprocessable()->assertJsonValidationErrors(['tipo_institucion', 'periodo_cursado']);
        $record = $context['student']->antecedentesAcademicos()->create([...$payload, 'tipo_institucion' => 'publica', 'periodo_cursado' => '2025']);
        $this->patchJson('/api/v1/student/antecedentes/'.$record->id, $payload)->assertUnprocessable()->assertJsonValidationErrors(['tipo_institucion', 'periodo_cursado']);
        $this->assertSame('2025', $record->fresh()->periodo_cursado);
    }

    public function test_student_can_update_a_historical_period_to_an_academic_cycle(): void
    {
        $context = $this->scenario();
        $this->asUser($context['student']);
        $record = $context['student']->antecedentesAcademicos()->create([
            'universidad_origen' => 'Universidad de origen', 'carrera_origen' => 'Software',
            'tipo_institucion' => 'publica', 'periodo_cursado' => '2025',
        ]);
        $this->patchJson('/api/v1/student/antecedentes/'.$record->id, ['periodo_cursado' => 'Séptimo ciclo / semestre'])
            ->assertOk()->assertJsonPath('data.periodo_cursado', 'Séptimo ciclo / semestre');
        $this->assertSame('Séptimo ciclo / semestre', $record->fresh()->periodo_cursado);
    }

    public static function roles(): array
    {
        return ['administrador' => ['administrador'], 'coordinador' => ['coordinador'], 'estudiante' => ['estudiante']];
    }

    public function test_coordinator_saves_distinct_origin_and_destination_and_updates_background(): void
    {
        Notification::fake();
        $context = $this->scenario();
        $modality = Modalidad::firstOrFail();
        $context['career']->modalidades()->attach($modality);
        $payload = [
            'nombres_completos' => 'Estudiante nuevo', 'tipo_identificacion' => 'cedula', 'cedula' => '1234567890',
            'email' => 'origin@example.test', 'numero_celular' => '0991234567',
            'carrera_id' => $context['career']->id, 'modalidad_id' => $modality->id,
            'carrera_origen_id' => $context['otherCareer']->id, 'universidad_origen' => 'Instituto de origen',
            'tipo_institucion' => 'instituto', 'periodo_cursado' => 'Primer ciclo / semestre',
        ];
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'carrera_origen_id' => $context['career']->id])->assertUnprocessable()->assertJsonValidationErrors('carrera_origen_id');
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'carrera_origen_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('carrera_origen_id');
        $this->postJson('/api/v1/coordinator/students', [...$payload, 'periodo_cursado' => 'Undécimo ciclo / semestre'])
            ->assertUnprocessable()->assertJsonValidationErrors('periodo_cursado');
        $id = $this->postJson('/api/v1/coordinator/students', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('antecedentes_academicos', ['estudiante_id' => $id, 'carrera_origen' => 'Industrial', 'tipo_institucion' => 'instituto']);
        $this->putJson('/api/v1/coordinator/students/'.$id, [...$payload, 'periodo_cursado' => 'Décimo ciclo / semestre'])->assertOk();
        $this->assertDatabaseHas('antecedentes_academicos', ['estudiante_id' => $id, 'periodo_cursado' => 'Décimo ciclo / semestre']);
        $this->assertSame(1, User::findOrFail($id)->antecedentesAcademicos()->count());
        Notification::assertSentTo(User::findOrFail($id), TemporaryPasswordNotification::class);
    }

    public function test_profile_accepts_only_ten_digit_phone_numbers(): void
    {
        $context = $this->scenario();
        $this->asUser($context['student']);
        foreach (['123456789', '12345678901', 'ABCDEFGHIJ'] as $phone) {
            $this->patchJson('/api/v1/student/profile', ['numero_celular' => $phone])->assertUnprocessable()->assertJsonValidationErrors('numero_celular');
        }
        $this->patchJson('/api/v1/student/profile', ['numero_celular' => '0991234567'])->assertOk();
        $this->assertSame('0991234567', $context['student']->fresh()->numero_celular);
    }

    public function test_administrator_deletes_unused_user_and_revokes_access(): void
    {
        $context = $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $user = User::factory()->create(['creador_id' => $admin->id]);
        $user->assignRole('coordinador');
        $user->coordinaciones()->create(['carrera_id' => $context['career']->id]);
        $token = $user->createToken('old');
        Password::createToken($user);
        DB::table('sessions')->insert(['id' => 'deleted-user-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $user->notifications()->create(['id' => Str::uuid()->toString(), 'type' => ResetPassword::class, 'data' => ['message' => 'Aviso']]);
        $this->asUser($admin);
        $this->deleteJson('/api/v1/admin/users/'.$user->id)->assertOk();
        $this->assertModelMissing($user);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('user_has_rol', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('coordinador_carreras', ['coordinador_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_administrator_cannot_delete_self_or_academic_history(): void
    {
        $context = $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->asUser($admin);
        $this->deleteJson('/api/v1/admin/users/'.$admin->id)->assertStatus(409);
        $this->deleteJson('/api/v1/admin/users/'.$context['student']->id)->assertStatus(409);
        $this->deleteJson('/api/v1/admin/users/'.$context['coordinator']->id)->assertStatus(409);
        $this->assertModelExists($context['student']);
        $this->assertModelExists($context['solicitud']);
    }

    public function test_user_deletion_requires_authentication_and_admin_role(): void
    {
        $this->deleteJson('/api/v1/admin/users/1')->assertUnauthorized();
        $context = $this->scenario();
        $this->deleteJson('/api/v1/admin/users/'.$context['student']->id)->assertForbidden();
        $this->asUser($context['student']);
        $this->deleteJson('/api/v1/admin/users/'.$context['coordinator']->id)->assertForbidden();
        $this->assertModelExists($context['student']);
        $this->assertModelExists($context['coordinator']);
    }

    public function test_referenced_user_deletion_returns_409_and_rolls_back_token_removal(): void
    {
        $context = $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $user = User::factory()->create();
        $context['solicitud']->documentos()->firstOrFail()->verificaciones()->create(['coordinador_id' => $user->id, 'estado' => true]);
        $token = $user->createToken('retained');
        $this->asUser($admin);
        $this->deleteJson('/api/v1/admin/users/'.$user->id)->assertStatus(409);
        $this->assertModelExists($user);
        $this->assertModelExists($token->accessToken);
    }

    public static function weakPasswords(): array
    {
        return ['corta' => ['abc1234'], 'sin numeros' => ['abcdefgh'], 'sin letras' => ['12345678']];
    }

    #[DataProvider('weakPasswords')]
    public function test_password_recovery_rejects_weak_password_with_422(string $password): void
    {
        $this->scenario();
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->postJson('/api/v1/reset-password', ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_initial_password_change_accepts_eight_characters_and_revokes_access(): void
    {
        $context = $this->scenario();
        $user = $context['student'];
        $user->forceFill(['must_change_password' => true])->save();
        $this->asUser($user);
        $this->postJson('/api/v1/change-password', ['current_password' => 'password', 'password' => 'Abcdef1!', 'password_confirmation' => 'Abcdef1!'])->assertOk();
        $this->assertTrue(Hash::check('Abcdef1!', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame(1, $user->tokens()->count());
    }

    #[DataProvider('roles')]
    public function test_password_recovery_accepts_eight_letters_and_numbers_for_every_role(string $role): void
    {
        Notification::fake();
        $this->scenario();
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->forceFill(['must_change_password' => true])->save();
        $token = null;
        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'Abcdef1!', 'password_confirmation' => 'Abcdef1!'];
        $this->postJson('/api/v1/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('Abcdef1!', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
