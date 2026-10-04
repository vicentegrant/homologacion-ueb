<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CoordinatorStudentService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialsController extends Controller
{
    public function resend(Request $request, User $user, UserService $service): JsonResponse
    {
        return $this->response($service->resendCredentials($user, $request->user()));
    }

    public function resendStudent(Request $request, int $student, CoordinatorStudentService $students, UserService $service): JsonResponse
    {
        return $this->response($service->resendCredentials($students->editable($request->user(), $student), $request->user()));
    }

    private function response(bool $sent): JsonResponse
    {
        return response()->json(['success' => true, 'credentials_email_sent' => $sent, 'message' => $sent ? 'Nuevas credenciales enviadas. La contraseña anterior dejó de ser válida.' : 'Se generó una nueva contraseña temporal, pero el correo no pudo enviarse. Reintente el envío o utilice Recuperar contraseña.']);
    }
}
