<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreResolutionRequest;
use App\Http\Resources\Api\ResolucionResource;
use App\Models\Solicitud;
use App\Services\ResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResolutionController extends Controller
{
    public function store(StoreResolutionRequest $request, Solicitud $solicitud, ResolutionService $resolutionService): JsonResponse
    {
        if ($solicitud->resolucion()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud ya tiene una resolución registrada.',
            ], 409);
        }

        $resolution = $resolutionService->create($solicitud, $request->user(), $request->validated());
        $resolution->load('coordinador');

        return response()->json([
            'success' => true,
            'message' => 'Resolución registrada correctamente.',
            'data' => ResolucionResource::make($resolution),
        ], 201);
    }

    public function download(Solicitud $solicitud): StreamedResponse|JsonResponse
    {
        $resolution = $solicitud->resolucion()->first();

        if ($resolution === null || ! Storage::disk('local')->exists($resolution->ruta_archivo)) {
            return response()->json(['success' => false, 'message' => 'Archivo de resolución no encontrado.'], 404);
        }

        return Storage::disk('local')->download(
            $resolution->ruta_archivo,
            $resolution->numero_resolucion.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
