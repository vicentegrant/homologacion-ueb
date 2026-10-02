<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentSolicitudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResolutionController extends Controller
{
    public function download(Request $request, int $solicitud, StudentSolicitudService $service): StreamedResponse
    {
        $record = $service->owned($request->user(), $solicitud);
        abort_unless($service->state($record) === 'listo', 409, 'La resolución todavía no está disponible para descarga.');
        $resolution = $record->resolucion;
        abort_unless($resolution && Storage::disk('local')->exists($resolution->ruta_archivo), 404);

        return Storage::disk('local')->download($resolution->ruta_archivo, 'resolucion-'.$record->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
