<?php

namespace App\Services;

use App\Models\EstadoSolicitud;
use App\Models\Solicitud;
use App\Models\SolicitudDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SolicitudWorkflowService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pendiente' => ['en_revision'],
        'en_revision' => ['observado', 'en_proceso', 'rechazado'],
        'observado' => ['en_revision', 'rechazado'],
        'en_proceso' => ['aprobado', 'rechazado'],
        'aprobado' => ['en_consejo'],
        'en_consejo' => ['listo', 'rechazado'],
        'listo' => [],
        'rechazado' => [],
    ];

    public function currentState(Solicitud $solicitud): ?string
    {
        return $solicitud->ultimoHistorialEstado()->with('estadoSolicitud')->first()?->estadoSolicitud?->nombre;
    }

    public function transition(Solicitud $solicitud, string $target, User $actor, ?string $observation = null): Solicitud
    {
        return DB::transaction(function () use ($solicitud, $target, $actor, $observation): Solicitud {
            $locked = Solicitud::query()->lockForUpdate()->findOrFail($solicitud->id);
            $current = $this->currentState($locked);

            abort_unless($current !== null && in_array($target, self::TRANSITIONS[$current] ?? [], true), 409, 'La transición de estado solicitada no es válida.');
            abort_if($target === 'rechazado' && blank($observation), 422, 'Debe indicar el motivo del rechazo.');
            $this->assertConditions($locked, $current, $target);

            $locked->historialEstados()->create([
                'estado_solicitud_id' => EstadoSolicitud::query()->where('nombre', $target)->firstOrFail()->id,
                'usuario_responsable_id' => $actor->id,
                'etapa_origen' => $current,
                'observacion' => $observation,
            ]);

            return $locked->refresh();
        });
    }

    public function requirementsApproved(Solicitud $solicitud): bool
    {
        $documents = $solicitud->documentos()->with(['estadoDocumento', 'verificaciones'])->get();

        return $documents->isNotEmpty() && $documents->every(function (SolicitudDocumento $document): bool {
            return $document->estadoDocumento->nombre === 'aprobado'
                && $document->validez
                && $document->ruta_documento_oficio !== null
                && Storage::disk('local')->exists($document->ruta_documento_oficio)
                && $document->verificaciones->isNotEmpty()
                && $document->verificaciones->every(fn ($verification): bool => $verification->estado);
        });
    }

    public function advanceToProcessingWhenReady(Solicitud $solicitud, User $actor): void
    {
        if ($this->currentState($solicitud) === 'en_revision' && $this->requirementsApproved($solicitud)) {
            $this->transition($solicitud, 'en_proceso', $actor, 'Documentación y requisitos aprobados.');
        }
    }

    /** @return array<string, list<string>> */
    public function transitions(): array
    {
        return self::TRANSITIONS;
    }

    private function assertConditions(Solicitud $solicitud, string $current, string $target): void
    {
        if ($current === 'pendiente' && $target === 'en_revision') {
            abort_unless($solicitud->estudiante->antecedentesAcademicos()->exists(), 409, 'El estudiante no ha registrado antecedentes académicos.');
            abort_unless($this->documentsReadyForReview($solicitud), 409, 'La documentación requerida no está completa.');
        }

        if ($target === 'observado') {
            abort_unless($solicitud->documentos()->whereHas('estadoDocumento', fn ($query) => $query->where('nombre', 'observado'))->exists(), 409, 'No existen documentos observados.');
        }

        if ($current === 'observado' && $target === 'en_revision') {
            abort_if($solicitud->documentos()->whereHas('estadoDocumento', fn ($query) => $query->where('nombre', 'observado'))->exists(), 409, 'Aún existen documentos observados sin corregir.');
            abort_unless($this->documentsReadyForReview($solicitud), 409, 'La documentación corregida no está completa.');
        }

        if ($target === 'en_proceso') {
            abort_unless($this->requirementsApproved($solicitud), 409, 'Los documentos y verificaciones obligatorias deben estar aprobados.');
        }

        if ($current === 'en_proceso' && in_array($target, ['aprobado', 'rechazado'], true)) {
            $expected = $target === 'aprobado' ? ['total', 'parcial'] : ['rechazada'];
            abort_unless($solicitud->resultado()->whereIn('conclusion_general', $expected)->exists(), 409, 'El resultado académico no respalda la transición.');
        }

        if ($target === 'en_consejo') {
            $result = $solicitud->resultado;
            abort_unless($result?->ruta_informe_tecnico && Storage::disk('local')->exists($result->ruta_informe_tecnico), 409, 'Debe generar el informe técnico antes de enviar a Consejo.');
        }

        if ($target === 'listo') {
            abort_unless($solicitud->resolucion()->exists(), 409, 'Debe registrar la resolución antes de finalizar la solicitud.');
        }
    }

    private function documentsReadyForReview(Solicitud $solicitud): bool
    {
        $documents = $solicitud->documentos()->with('estadoDocumento')->get();

        return $documents->isNotEmpty() && $documents->every(fn (SolicitudDocumento $document): bool => $document->ruta_documento_oficio !== null
            && Storage::disk('local')->exists($document->ruta_documento_oficio)
            && in_array($document->estadoDocumento->nombre, ['presentado', 'aprobado'], true));
    }
}
