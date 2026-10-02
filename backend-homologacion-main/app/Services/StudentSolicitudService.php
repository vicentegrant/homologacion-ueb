<?php

namespace App\Services;

use App\Models\DocumentoRequeridoProceso;
use App\Models\EstadoDocumento;
use App\Models\EstadoSolicitud;
use App\Models\Solicitud;
use App\Models\SolicitudDocumento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentSolicitudService
{
    public function __construct(private SolicitudWorkflowService $workflow) {}

    /** @return list<string> */
    public function relations(): array
    {
        return ['carrera', 'coordinador', 'tramiteProceso.tipoTramite', 'tramiteProceso.tipoProceso',
            'ultimoHistorialEstado.estadoSolicitud', 'documentos.documentoRequerido',
            'documentos.estadoDocumento', 'documentos.observaciones', 'historialEstados.estadoSolicitud',
            'resolucion', 'resultado'];
    }

    public function owned(User $student, int $id, bool $lock = false): Solicitud
    {
        return $student->solicitudesComoEstudiante()->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->findOrFail($id);
    }

    public function state(Solicitud $solicitud): ?string
    {
        return $this->workflow->currentState($solicitud);
    }

    /** @param array{coordinador_carrera_id: int, tramite_proceso_id: int, procedencia_estudios: string} $data */
    public function create(User $student, array $data): Solicitud
    {
        return DB::transaction(function () use ($student, $data): Solicitud {
            User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $enrollment = $student->carrerasComoEstudiante()
                ->where('coordinador_carrera_id', $data['coordinador_carrera_id'])->firstOrFail();
            $assignment = $enrollment->coordinadorCarrera()->lockForUpdate()->firstOrFail();
            $coordinator = $assignment->coordinador;
            abort_unless($coordinator?->cuenta_activa && $coordinator->hasRole('coordinador'), 409, 'La carrera no tiene un coordinador activo.');

            $existing = $student->solicitudesComoEstudiante()->where('carrera_id', $assignment->carrera_id)
                ->where('tramite_proceso_id', $data['tramite_proceso_id'])
                ->whereHas('ultimoHistorialEstado.estadoSolicitud', fn (Builder $query): Builder => $query->whereNotIn('nombre', ['listo', 'rechazado']))
                ->exists();
            abort_if($existing, 409, 'Ya tiene una solicitud activa para esta carrera y trámite.');

            $requirements = DocumentoRequeridoProceso::query()->where('tramite_proceso_id', $data['tramite_proceso_id'])
                ->where(fn (Builder $query): Builder => $query->whereNull('carrera_id')->orWhere('carrera_id', $assignment->carrera_id))->get();
            abort_if($requirements->isEmpty(), 409, 'No hay documentos requeridos configurados para esta carrera y trámite.');

            $solicitud = $student->solicitudesComoEstudiante()->create([
                'coordinador_id' => $coordinator->id, 'carrera_id' => $assignment->carrera_id,
                'tramite_proceso_id' => $data['tramite_proceso_id'], 'procedencia_estudios' => $data['procedencia_estudios'],
            ]);
            $pendingDocument = EstadoDocumento::query()->where('nombre', 'pendiente')->firstOrFail();
            foreach ($requirements as $requirement) {
                $solicitud->documentos()->create([
                    'documento_requerido_proceso_id' => $requirement->id,
                    'estado_documento_id' => $pendingDocument->id,
                ]);
            }
            $this->recordState($solicitud, 'pendiente', 'Solicitud creada por el estudiante.', $student);

            return $solicitud;
        });
    }

    /** @param array{procedencia_estudios?: string} $data */
    public function update(User $student, int $id, array $data): Solicitud
    {
        return DB::transaction(function () use ($student, $id, $data): Solicitud {
            $solicitud = $this->owned($student, $id, true);
            abort_unless($this->state($solicitud) === 'pendiente', 409, 'Solo puede editar una solicitud pendiente.');
            $solicitud->update($data);

            return $solicitud;
        });
    }

    public function submit(User $student, int $id): Solicitud
    {
        return DB::transaction(function () use ($student, $id): Solicitud {
            $solicitud = $this->owned($student, $id, true);
            abort_unless(in_array($this->state($solicitud), ['pendiente', 'observado'], true), 409, 'La solicitud no permite un envío a revisión en su estado actual.');
            $coordinator = $solicitud->coordinador;
            abort_unless($coordinator?->cuenta_activa && $coordinator->hasRole('coordinador'), 409, 'La solicitud no tiene un coordinador activo.');
            if (! $student->antecedentesAcademicos()->exists()) {
                throw ValidationException::withMessages(['antecedentes' => 'Registre sus antecedentes académicos antes de enviar la solicitud.']);
            }
            $documents = $solicitud->documentos()->with('estadoDocumento')->get();
            $missing = $documents->filter(fn (SolicitudDocumento $document): bool => ! $document->ruta_documento_oficio
                || ! in_array($document->estadoDocumento->nombre, ['presentado', 'aprobado'], true)
                || ($document->estadoDocumento->nombre === 'aprobado' && ! $document->validez)
                || ! Storage::disk('local')->exists($document->ruta_documento_oficio));
            if ($documents->isEmpty() || $missing->isNotEmpty()) {
                throw ValidationException::withMessages(['documentos' => 'Complete o corrija todos los documentos requeridos antes de enviar.']);
            }
            $this->workflow->transition($solicitud, 'en_revision', $student, 'Documentación enviada a revisión por el estudiante.');

            return $solicitud;
        });
    }

    private function recordState(Solicitud $solicitud, string $state, string $message, User $actor): void
    {
        $solicitud->historialEstados()->create([
            'estado_solicitud_id' => EstadoSolicitud::query()->where('nombre', $state)->firstOrFail()->id,
            'usuario_responsable_id' => $actor->id,
            'observacion' => $message,
        ]);
    }
}
