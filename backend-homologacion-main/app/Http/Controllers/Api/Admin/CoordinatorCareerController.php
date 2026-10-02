<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AssignCoordinatorCareersRequest;
use App\Http\Resources\Api\CareerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CoordinatorCareerController extends Controller
{
    public function index(User $coordinator): JsonResponse
    {
        if (! $coordinator->hasRole('coordinador')) {
            return response()->json(['success' => false, 'message' => 'El usuario no es Coordinador.'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => CareerResource::collection($coordinator->carrerasCoordinadas()->orderBy('nombre')->get()),
        ]);
    }

    public function update(AssignCoordinatorCareersRequest $request, User $coordinator): JsonResponse
    {
        if (! $coordinator->hasRole('coordinador')) {
            return response()->json(['success' => false, 'message' => 'El usuario no es Coordinador.'], 422);
        }

        $updated = DB::transaction(function () use ($request, $coordinator): bool {
            $careerIds = $request->validated('carrera_ids');
            $assignments = $coordinator->coordinaciones()->lockForUpdate()->get();

            foreach ($assignments as $assignment) {
                if (! in_array($assignment->carrera_id, $careerIds) && $assignment->estudiantes()->exists()) {
                    return false;
                }
            }

            $coordinator->carrerasCoordinadas()->sync($careerIds);

            return true;
        });

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'No puede retirar carreras con estudiantes asignados. Reasigne los estudiantes primero.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carreras asignadas correctamente.',
            'data' => CareerResource::collection($coordinator->carrerasCoordinadas()->orderBy('nombre')->get()),
        ]);
    }
}
