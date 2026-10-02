<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\StoreAcademicBackgroundRequest;
use App\Http\Requests\Api\Student\UpdateAcademicBackgroundRequest;
use App\Http\Resources\Api\AcademicBackgroundResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcademicBackgroundController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100']]);
        $records = $request->user()->antecedentesAcademicos()
            ->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return AcademicBackgroundResource::collection($records)->additional(['success' => true]);
    }

    public function store(StoreAcademicBackgroundRequest $request): JsonResponse
    {
        $record = $request->user()->antecedentesAcademicos()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Antecedente académico registrado correctamente.',
            'data' => AcademicBackgroundResource::make($record),
        ], 201);
    }

    public function show(Request $request, int $antecedente): JsonResponse
    {
        $record = $request->user()->antecedentesAcademicos()->findOrFail($antecedente);

        return response()->json(['success' => true, 'data' => AcademicBackgroundResource::make($record)]);
    }

    public function update(UpdateAcademicBackgroundRequest $request, int $antecedente): JsonResponse
    {
        $record = $request->user()->antecedentesAcademicos()->findOrFail($antecedente);
        $record->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Antecedente académico actualizado correctamente.',
            'data' => AcademicBackgroundResource::make($record),
        ]);
    }
}
