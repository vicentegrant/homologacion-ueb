<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification as ResetPassword;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_login_is_restricted_until_change_and_all_tokens_are_revoked(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['password' => 'Temporary123456']);
        $user->assignRole('estudiante');
        $user->forceFill(['must_change_password' => true, 'temporary_password_expires_at' => now()->addDay()])->save();
        $oldToken = $user->createToken('other');
        $token = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'Temporary123456'])->assertOk()->assertJsonPath('user.must_change_password', true)->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/v1/student/profile')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->postJson('/api/v1/change-password', ['current_password' => 'wrong', 'password' => 'Permanent123456!', 'password_confirmation' => 'Permanent123456!'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/v1/change-password', ['current_password' => 'Temporary123456', 'password' => 'Permanent123456!', 'password_confirmation' => 'Permanent123456!'])->assertOk();
        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertNull($user->temporary_password_expires_at);
        $this->assertTrue(Hash::check('Permanent123456!', $user->password));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertModelMissing($oldToken->accessToken);
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_expired_temporary_password_cannot_login_or_change_with_existing_token(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['password' => 'Temporary123456']);
        $user->forceFill(['must_change_password' => true, 'temporary_password_expires_at' => now()->subMinute()])->save();
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'Temporary123456'])->assertUnauthorized()->assertJsonPath('message', 'Credenciales incorrectas.');
        $this->withToken($user->createToken('old')->plainTextToken)->postJson('/api/v1/change-password', ['current_password' => 'Temporary123456', 'password' => 'Permanent123456!', 'password_confirmation' => 'Permanent123456!'])->assertUnprocessable();
    }

    public function test_recovery_does_not_disclose_accounts_and_token_is_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();
        $user->createToken('old');
        $known = $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk()->json();
        $this->postJson('/api/v1/forgot-password', ['email' => 'missing@example.com'])->assertOk()->assertExactJson($known);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'Permanent123456!', 'password_confirmation' => 'Permanent123456!'];
        $this->postJson('/api/v1/reset-password', $payload)->assertOk();
        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('Permanent123456!', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/reset-password', $payload)->assertUnprocessable();
    }

    public function test_expired_reset_tokens_and_inactive_accounts_are_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Permanent123456!', 'password_confirmation' => 'Permanent123456!'])->assertUnprocessable();
        $user->update(['cuenta_activa' => false]);
        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertNothingSent();
    }

    public function test_password_endpoints_reject_unauthorized_or_weak_changes(): void
    {
        $this->postJson('/api/v1/change-password')->assertUnauthorized();
        $this->postJson('/api/v1/reset-password', ['email' => 'invalid', 'password' => 'short'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'token', 'password']);
        $user = User::factory()->create(['password' => 'Temporary123456']);
        $this->withToken($user->createToken('test')->plainTextToken)->postJson('/api/v1/change-password', ['current_password' => 'Temporary123456', 'password' => 'Temporary123456', 'password_confirmation' => 'Temporary123456'])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
