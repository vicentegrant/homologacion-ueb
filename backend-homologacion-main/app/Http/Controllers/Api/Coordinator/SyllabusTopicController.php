<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\StoreSyllabusTopicRequest;
use App\Http\Requests\Api\Coordinator\UpdateSyllabusTopicRequest;
use App\Services\CoordinatorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyllabusTopicController extends Controller
{
    public function index(Request $request, int $asignatura, CoordinatorAccessService $access): JsonResponse
    {
        $subject = $access->subject($request->user(), $asignatura);

        return response()->json(['success' => true, 'data' => $subject->temasSilabo()->orderBy('id')->get()]);
    }

    public function store(StoreSyllabusTopicRequest $request, int $asignatura, CoordinatorAccessService $access): JsonResponse
    {
        $subject = $access->subject($request->user(), $asignatura);
        $topic = $subject->temasSilabo()->create($request->validated());

        return response()->json(['success' => true, 'message' => 'Tema de sílabo registrado correctamente.', 'data' => $topic], 201);
    }

    public function update(UpdateSyllabusTopicRequest $request, int $tema, CoordinatorAccessService $access): JsonResponse
    {
        $topic = $access->syllabusTopic($request->user(), $tema);
        $topic->update($request->validated());

        return response()->json(['success' => true, 'message' => 'Tema de sílabo actualizado correctamente.', 'data' => $topic]);
    }
}
