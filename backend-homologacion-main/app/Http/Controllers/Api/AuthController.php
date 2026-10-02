<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'El registro público no está habilitado. Solicite su cuenta al Administrador.',
        ], 403);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Credenciales incorrectas.'], 401);
        }

        if (! $user->cuenta_activa) {
            return response()->json(['success' => false, 'message' => 'La cuenta se encuentra inactiva.'], 403);
        }

        $token = (string) $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $this->userData($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'user' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sesión cerrada.']);
    }

    public function roles(): JsonResponse
    {
        $roles = Role::query()->orderBy('nombre')->get(['id', 'nombre']);

        return response()->json(['success' => true, 'roles' => $roles->pluck('nombre'), 'data' => $roles]);
    }

    /** @return array{id: int, nombres_completos: string, cedula: ?string, email: string, numero_celular: ?string, cuenta_activa: bool, roles: list<string>, carreras_coordinadas?: list<array{id: int, nombre: string}>} */
    private function userData(User $user): array
    {
        $data = [
            'id' => $user->id,
            'nombres_completos' => $user->nombres_completos,
            'cedula' => $user->cedula,
            'email' => $user->email,
            'numero_celular' => $user->numero_celular,
            'cuenta_activa' => $user->cuenta_activa,
            'roles' => $user->getRoleNames()->values()->all(),
        ];

        if ($user->hasRole('coordinador')) {
            $data['carreras_coordinadas'] = $this->coordinatedCareers($user);
        }

        return $data;
    }

    /** @return list<array{id: int, nombre: string}> */
    private function coordinatedCareers(User $user): array
    {
        return $user->carrerasCoordinadas()
            ->orderBy('nombre')
            ->get(['carreras.id', 'carreras.nombre'])
            ->map(fn (Carrera $career): array => ['id' => $career->id, 'nombre' => $career->nombre])
            ->values()
            ->all();
    }
}
