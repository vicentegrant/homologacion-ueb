<?php

namespace Tests\Feature\Coordinator;

use App\Models\AsignaturaCredito;
use App\Models\MallaCurricular;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class WorkflowResolutionTest extends CoordinatorWorkflowTestCase
{
    public function test_invalid_state_transitions_return_conflict_without_changing_history(): void
    {
        $context = $this->scenario('pendiente');
        $count = $context['solicitud']->historialEstados()->count();

        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/state', [
            'estado' => 'listo',
        ])->assertStatus(409)->assertJsonPath('success', false);

        $this->assertSame($count, $context['solicitud']->historialEstados()->count());
        $this->assertSame('pendiente', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
    }

    public function test_complete_analysis_report_council_and_resolution_flow_finishes_request(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])->assertOk();
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertOk();
        [$origin, $destination] = $this->subjects($context);
        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/comparisons', [
            'asignatura_origen_id' => $origin->id, 'asignatura_destino_id' => $destination->id,
            'porcentaje_coincidencia' => 90, 'observacion' => 'Equivalencia técnica.',
        ])->assertCreated();

        $resultUrl = '/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/result';
        $this->postJson($resultUrl, [
            'conclusion_general' => 'total', 'total_creditos_reconocidos' => 4,
        ])->assertCreated()->assertJsonPath('data.conclusion_general', 'total');
        $this->postJson($resultUrl, [
            'conclusion_general' => 'parcial', 'total_creditos_reconocidos' => 2,
        ])->assertStatus(409);
        $this->assertSame('aprobado', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);

        $reportUrl = '/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/technical-report';
        $this->postJson($reportUrl)->assertCreated()->assertJsonPath('data.informe_tecnico_disponible', true);
        $path = $context['solicitud']->fresh()->resultado->ruta_informe_tecnico;
        Storage::disk('local')->assertExists($path);
        $this->get($reportUrl, ['Accept' => 'application/json'])->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/state', [
            'estado' => 'en_consejo', 'observacion' => 'Informe remitido a Consejo.',
        ])->assertOk()->assertJsonPath('data.estado_actual.nombre', 'en_consejo');
        $this->post('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/resolution', [
            'numero_resolucion' => 'RES-2026-100', 'fecha_aprobacion' => '2026-09-23',
            'archivo' => UploadedFile::fake()->createWithContent('resolucion.pdf', "%PDF-1.4\nresolucion"),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.numero_resolucion', 'RES-2026-100');

        $this->assertSame('listo', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->assertDatabaseHas('resoluciones_solicitud', ['solicitud_id' => $context['solicitud']->id, 'coordinador_id' => $context['coordinator']->id]);
        $this->asUser($context['student']);
        $this->get('/api/v1/student/solicitudes/'.$context['solicitud']->id.'/resolucion/download', ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_rejected_result_records_terminal_state_and_responsible_coordinator(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])->assertOk();
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertOk();
        [$origin, $destination] = $this->subjects($context);
        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/comparisons', [
            'asignatura_origen_id' => $origin->id, 'asignatura_destino_id' => $destination->id,
            'porcentaje_coincidencia' => 10,
        ])->assertCreated();

        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/result', [
            'conclusion_general' => 'rechazada', 'total_creditos_reconocidos' => 0,
        ])->assertCreated();

        $history = $context['solicitud']->fresh()->ultimoHistorialEstado;
        $this->assertSame('rechazado', $history->estadoSolicitud->nombre);
        $this->assertSame($context['coordinator']->id, $history->usuario_responsable_id);
        $this->assertSame('Resultado académico rechazado.', $history->observacion);
        $this->assertSame('en_proceso', $history->etapa_origen);
    }

    public function test_partial_result_is_persisted_and_approves_the_academic_stage(): void
    {
        $context = $this->scenario();
        $document = $context['solicitud']->documentos()->firstOrFail();
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])->assertOk();
        $this->postJson('/api/v1/coordinator/documents/'.$document->id.'/verification', ['estado' => true])->assertOk();
        [$origin, $destination] = $this->subjects($context);
        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/comparisons', [
            'asignatura_origen_id' => $origin->id,
            'asignatura_destino_id' => $destination->id,
            'porcentaje_coincidencia' => 65,
        ])->assertCreated();

        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/result', [
            'conclusion_general' => 'parcial',
            'total_creditos_reconocidos' => 2,
        ])->assertCreated()->assertJsonPath('data.conclusion_general', 'parcial');

        $solicitud = $context['solicitud']->fresh();
        $this->assertSame('parcial', $solicitud->resultado->conclusion_general);
        $this->assertSame('aprobado', $solicitud->ultimoHistorialEstado->estadoSolicitud->nombre);
    }

    public function test_rejection_is_allowed_during_document_review_and_records_origin_stage(): void
    {
        $context = $this->scenario('en_revision');

        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/state', [
            'estado' => 'rechazado',
            'observacion' => 'Documentación inconsistente.',
        ])->assertOk()->assertJsonPath('data.estado_actual.nombre', 'rechazado');

        $history = $context['solicitud']->fresh()->ultimoHistorialEstado;
        $this->assertSame('en_revision', $history->etapa_origen);
        $this->assertSame('Documentación inconsistente.', $history->observacion);
    }

    public function test_rejection_is_allowed_during_council_stage_and_records_origin_stage(): void
    {
        $context = $this->scenario('en_consejo');

        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/state', [
            'estado' => 'rechazado',
            'observacion' => 'Consejo negó la homologación.',
        ])->assertOk()->assertJsonPath('data.estado_actual.nombre', 'rechazado');

        $history = $context['solicitud']->fresh()->ultimoHistorialEstado;
        $this->assertSame('en_consejo', $history->etapa_origen);
        $this->assertSame('Consejo negó la homologación.', $history->observacion);
    }

    /** @param array<string, mixed> $context
     * @return array{AsignaturaCredito, AsignaturaCredito}
     */
    private function subjects(array $context): array
    {
        $originCurriculum = MallaCurricular::query()->create([
            'nombre' => 'Origen', 'tipo' => 'origen', 'carrera_id' => $context['career']->id,
            'creador_id' => $context['coordinator']->id, 'estudiante_id' => $context['student']->id,
        ]);
        $destinationCurriculum = MallaCurricular::query()->create([
            'nombre' => 'Destino', 'tipo' => 'institucional', 'carrera_id' => $context['career']->id,
            'creador_id' => $context['coordinator']->id,
        ]);
        $attributes = ['nombre_asignatura' => 'Materia', 'numero_creditos' => 4, 'nivel_ciclo' => 'primero', 'hr_carga_horaria' => 64];

        return [
            $originCurriculum->asignaturas()->create([...$attributes, 'codigo_asignatura' => 'ORI-1']),
            $destinationCurriculum->asignaturas()->create([...$attributes, 'codigo_asignatura' => 'DES-1']),
        ];
    }
}
