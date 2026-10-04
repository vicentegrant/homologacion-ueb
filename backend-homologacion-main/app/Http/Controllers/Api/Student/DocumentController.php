<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentSolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(Request $request, int $solicitud, int $documento, StudentSolicitudService $service): JsonResponse
    {
        $service->owned($request->user(), $solicitud)->documentos()->findOrFail($documento);
        abort(410, 'La entrega es presencial. El coordinador registra y valida los documentos.');
    }

    public function download(Request $request, int $solicitud, int $documento, StudentSolicitudService $service): StreamedResponse
    {
        $document = $service->owned($request->user(), $solicitud)->documentos()->findOrFail($documento);
        abort_unless($document->ruta_documento_oficio && Storage::disk('local')->exists($document->ruta_documento_oficio), 404);

        return Storage::disk('local')->download($document->ruta_documento_oficio, 'documento-'.$document->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
