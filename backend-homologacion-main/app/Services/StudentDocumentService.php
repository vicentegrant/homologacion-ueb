<?php

namespace App\Services;

use App\Models\EstadoDocumento;
use App\Models\SolicitudDocumento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StudentDocumentService
{
    public function __construct(private StudentSolicitudService $solicitudes) {}

    public function store(User $student, int $solicitudId, int $documentId, UploadedFile $file): SolicitudDocumento
    {
        $newPath = null;
        $oldPath = null;
        try {
            $document = DB::transaction(function () use ($student, $solicitudId, $documentId, $file, &$newPath, &$oldPath): SolicitudDocumento {
                $solicitud = $this->solicitudes->owned($student, $solicitudId, true);
                $document = $solicitud->documentos()->with('estadoDocumento')->findOrFail($documentId);
                $state = $this->solicitudes->state($solicitud);
                $documentState = $document->estadoDocumento->nombre;
                $editable = ($state === 'pendiente' && in_array($documentState, ['pendiente', 'presentado'], true))
                    || ($state === 'observado' && in_array($documentState, ['pendiente', 'observado'], true));
                abort_unless($editable, 409, 'Este documento no admite cambios en su estado actual.');
                $newPath = $file->store('solicitudes/'.$solicitud->id, 'local');
                if (! is_string($newPath)) {
                    throw new RuntimeException('No fue posible almacenar el documento.');
                }
                $oldPath = $document->ruta_documento_oficio;
                $document->verificaciones()->delete();
                $document->update([
                    'ruta_documento_oficio' => $newPath,
                    'estado_documento_id' => EstadoDocumento::query()->where('nombre', 'presentado')->firstOrFail()->id,
                    'validez' => false,
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            if (is_string($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath) {
            try {
                Storage::disk('local')->delete($oldPath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $document->load(['documentoRequerido', 'estadoDocumento', 'observaciones']);
    }
}
