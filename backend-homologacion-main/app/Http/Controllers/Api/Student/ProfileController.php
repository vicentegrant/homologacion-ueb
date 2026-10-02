<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\UpdateProfileRequest;
use App\Http\Resources\Api\StudentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->profile($request->user())]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $student = $request->user();
        $student->fill($request->validated());
        $student->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'data' => $this->profile($student),
        ]);
    }

    private function profile(User $student): StudentResource
    {
        return StudentResource::make($student->load([
            'antecedentesAcademicos',
            'carrerasComoEstudiante.coordinadorCarrera.carrera',
            'carrerasComoEstudiante.coordinadorCarrera.coordinador',
        ]));
    }
}
