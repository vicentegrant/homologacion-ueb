<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coordinator\ReviewDocumentRequest;
use App\Http\Requests\Api\Coordinator\VerifyDocumentRequest;
use App\Http\Resources\Api\CoordinatorDocumentResource;
use App\Services\CoordinatorAccessService;
use App\Services\CoordinatorDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request, int $solicitud, CoordinatorAccessService $access): JsonResponse
    {
        $record = $access->solicitud($request->user(), $solicitud);
        $documents = $record->documentos()->with(['documentoRequerido', 'estadoDocumento', 'observaciones', 'verificaciones.coordinador'])->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => CoordinatorDocumentResource::collection($documents)]);
    }

    public function show(Request $request, int $documento, CoordinatorAccessService $access): JsonResponse
    {
        $document = $access->document($request->user(), $documento)
            ->load(['documentoRequerido', 'estadoDocumento', 'observaciones', 'verificaciones.coordinador']);

        return response()->json(['success' => true, 'data' => CoordinatorDocumentResource::make($document)]);
    }

    public function download(Request $request, int $documento, CoordinatorAccessService $access): StreamedResponse
    {
        $document = $access->document($request->user(), $documento);
        abort_unless($document->ruta_documento_oficio && Storage::disk('local')->exists($document->ruta_documento_oficio), 404);

        return Storage::disk('local')->download($document->ruta_documento_oficio, 'documento-'.$document->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function review(ReviewDocumentRequest $request, int $documento, CoordinatorDocumentService $service): JsonResponse
    {
        $data = $request->validated();
        $document = $service->review($request->user(), $documento, $data['estado'], $data['observacion'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $data['estado'] === 'aprobado' ? 'Documento aprobado correctamente.' : 'Documento observado correctamente.',
            'data' => CoordinatorDocumentResource::make($document),
        ]);
    }

    public function verify(VerifyDocumentRequest $request, int $documento, CoordinatorDocumentService $service): JsonResponse
    {
        $document = $service->verify($request->user(), $documento, $request->boolean('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Verificación registrada correctamente.',
            'data' => CoordinatorDocumentResource::make($document),
        ]);
    }
}
