<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AnalysisResultResource;
use App\Services\CoordinatorAccessService;
use App\Services\TechnicalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicalReportController extends Controller
{
    public function store(Request $request, int $solicitud, CoordinatorAccessService $access, TechnicalReportService $reports): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $reports->generate($request->user(), $record);

        return response()->json([
            'success' => true,
            'message' => 'Informe técnico generado correctamente.',
            'data' => AnalysisResultResource::make($record->resultado()->with('coordinador')->firstOrFail()),
        ], 201);
    }

    public function download(Request $request, int $solicitud, CoordinatorAccessService $access): StreamedResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $result = $record->resultado;
        abort_unless($result?->ruta_informe_tecnico && Storage::disk('local')->exists($result->ruta_informe_tecnico), 404);

        return Storage::disk('local')->download($result->ruta_informe_tecnico, 'informe-tecnico-'.$record->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
