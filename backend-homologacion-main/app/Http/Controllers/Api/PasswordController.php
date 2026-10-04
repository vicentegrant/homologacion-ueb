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
            'password' => ['required', 'string', 'confirmed', 'different:current_password', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña actual es incorrecta.']);
            }
            if ($user->must_change_password && $user->temporary_password_expires_at?->isPast()) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña temporal caducó. Utilice Recuperar contraseña.']);
            }
            $this->savePassword($user, $data['password']);
            Password::deleteToken($user);
        });

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada. Inicie sesión nuevamente.']);
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
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $status = DB::transaction(function () use ($data): string {
            User::query()->where('email', $data['email'])->lockForUpdate()->first();

            return Password::reset([...$data, 'cuenta_activa' => true], function (User $user, string $password): void {
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
        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => false, 'temporary_password_expires_at' => null, 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
