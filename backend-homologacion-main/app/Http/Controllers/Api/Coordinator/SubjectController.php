<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\StoreSubjectRequest;
use App\Http\Requests\Api\Coordinator\UpdateSubjectRequest;
use App\Http\Resources\Api\SubjectResource;
use App\Services\CoordinatorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, int $malla, CoordinatorAccessService $access): AnonymousResourceCollection
    {
        $curriculum = $access->curriculum($request->user(), $malla);
        $subjects = $curriculum->asignaturas()->with('temasSilabo')->orderBy('nivel_ciclo')->orderBy('codigo_asignatura')->paginate(50);

        return SubjectResource::collection($subjects)->additional(['success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSubjectRequest $request, int $malla, CoordinatorAccessService $access): JsonResponse
    {
        $curriculum = $access->curriculum($request->user(), $malla);
        abort_if($curriculum->asignaturas()->where('codigo_asignatura', $request->string('codigo_asignatura'))->exists(), 409, 'El código de asignatura ya existe en la malla.');
        $subject = $curriculum->asignaturas()->create($request->validated());

        return response()->json(['success' => true, 'message' => 'Asignatura creada correctamente.', 'data' => SubjectResource::make($subject)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $asignatura, CoordinatorAccessService $access): JsonResponse
    {
        $subject = $access->subject($request->user(), $asignatura)->load(['mallaCurricular.carrera', 'temasSilabo']);

        return response()->json(['success' => true, 'data' => SubjectResource::make($subject)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubjectRequest $request, int $asignatura, CoordinatorAccessService $access): JsonResponse
    {
        $subject = $access->subject($request->user(), $asignatura);
        $code = $request->validated('codigo_asignatura');
        if ($code !== null) {
            abort_if($subject->mallaCurricular->asignaturas()->where('codigo_asignatura', $code)->where('id', '!=', $subject->id)->exists(), 409, 'El código de asignatura ya existe en la malla.');
        }
        $subject->update($request->validated());

        return response()->json(['success' => true, 'message' => 'Asignatura actualizada correctamente.', 'data' => SubjectResource::make($subject->load('temasSilabo'))]);
    }
}
