<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StudentIndexRequest;
use App\Http\Resources\Api\StudentResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentController extends Controller
{
    public function index(StudentIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $students = User::query()
            ->role('estudiante')
            ->with(['antecedentesAcademicos'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('nombres_completos', 'like', "%{$search}%")
                        ->orWhere('cedula', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists('cuenta_activa', $filters), fn (Builder $query) => $query->where('cuenta_activa', $filters['cuenta_activa']))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return StudentResource::collection($students)->additional(['success' => true]);
    }

    public function show(User $student): JsonResponse
    {
        if (! $student->hasRole('estudiante')) {
            return response()->json(['success' => false, 'message' => 'Estudiante no encontrado.'], 404);
        }

        $student->load([
            'antecedentesAcademicos',
            'carrerasComoEstudiante.coordinadorCarrera.carrera',
            'carrerasComoEstudiante.coordinadorCarrera.coordinador',
            'solicitudesComoEstudiante.tramiteProceso.tipoTramite',
            'solicitudesComoEstudiante.tramiteProceso.tipoProceso',
            'solicitudesComoEstudiante.ultimoHistorialEstado.estadoSolicitud',
            'solicitudesComoEstudiante.coordinador',
            'solicitudesComoEstudiante.resolucion.coordinador',
        ]);

        return response()->json(['success' => true, 'data' => StudentResource::make($student)]);
    }
}
