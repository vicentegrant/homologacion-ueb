<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function change(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $token = (string) DB::transaction(function () use ($request, $data): string {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña actual es incorrecta.']);
            }
            // Se impide reutilizar la identificación incluso después del primer cambio.
            $this->ensureNewPassword($user, $data['password']);
            if ($user->must_change_password && $user->temporary_password_expires_at?->isPast()) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña temporal caducó. Utilice Recuperar contraseña.']);
            }
            $this->savePassword($user, $data['password']);
            Password::deleteToken($user);

            // Se revocan los accesos anteriores y se entrega un token nuevo al cliente.
            return $user->createToken('frontend')->plainTextToken;
        });

        if ($request->hasSession()) {
            // Evita conservar el identificador de una sesión anterior al cambio.
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada.', 'token' => $token, 'token_type' => 'Bearer']);
    }

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink([...$data, 'cuenta_activa' => true]);

        return response()->json(['success' => true, 'message' => 'Si el correo corresponde a una cuenta activa, recibirá un enlace para recuperar su contraseña.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $status = DB::transaction(function () use ($data): string {
            User::query()->where('email', $data['email'])->lockForUpdate()->first();

            return Password::reset([...$data, 'cuenta_activa' => true], function (User $user, string $password): void {
                $this->ensureNewPassword($user, $password);
                $this->savePassword($user, $password);
                event(new PasswordReset($user));
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ha caducado. Solicite uno nuevo.']);
        }

        return response()->json(['success' => true, 'message' => 'Contraseña recuperada. Inicie sesión nuevamente.']);
    }

    private function savePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => false, 'password_changed_at' => now(), 'temporary_password_expires_at' => null, 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function ensureNewPassword(User $user, string $password): void
    {
        if ($password === $user->cedula || Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'La nueva contraseña debe ser distinta a su identificación y a la contraseña actual.']);
        }
    }
}
