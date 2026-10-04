<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // El bloqueo se aplica en la API aunque se manipule la navegación del frontend.
        if ($request->user()?->must_change_password) {
            return response()->json(['success' => false, 'code' => 'PASSWORD_CHANGE_REQUIRED', 'message' => 'Debe cambiar su contraseña temporal antes de continuar.'], 403);
        }

        return $next($request);
    }
}
