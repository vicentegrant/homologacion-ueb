<?php

namespace Tests\Feature\Coordinator;

use App\Models\AsignaturaCredito;
use App\Models\MallaCurricular;

class CurriculumAnalysisTest extends CoordinatorWorkflowTestCase
{
    public function test_coordinator_manages_authorized_curricula_subjects_and_syllabus_topics(): void
    {
        $context = $this->scenario();
        $institutional = $this->postJson('/api/v1/coordinator/curricula', [
            'nombre' => 'Malla institucional 2026', 'tipo' => 'institucional',
            'carrera_id' => $context['career']->id, 'activa' => true,
        ])->assertCreated()->json('data.id');
        $origin = $this->postJson('/api/v1/coordinator/curricula', [
            'nombre' => 'Malla de origen', 'tipo' => 'origen',
            'carrera_id' => $context['career']->id, 'estudiante_id' => $context['student']->id,
        ])->assertCreated()->json('data.id');

        $subject = $this->postJson('/api/v1/coordinator/curricula/'.$institutional.'/subjects', [
            'codigo_asignatura' => 'MAT-101', 'nombre_asignatura' => 'Matemática',
            'numero_creditos' => 4, 'nivel_ciclo' => 'primero', 'hr_carga_horaria' => 64,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/coordinator/subjects/'.$subject.'/syllabus-topics', [
            'tema' => 'Álgebra lineal', 'unidad_analitica' => 'Unidad 1',
        ])->assertCreated();

        $this->assertDatabaseHas('mallas_curriculares', ['id' => $origin, 'estudiante_id' => $context['student']->id]);
        $this->assertDatabaseHas('temas_silabo_asignaturas', ['asignatura_id' => $subject, 'tema' => 'Álgebra lineal']);
        $this->getJson('/api/v1/coordinator/curricula/'.$institutional)->assertOk()->assertJsonCount(1, 'data.asignaturas');
    }

    public function test_coordinator_cannot_create_or_modify_curriculum_from_another_career(): void
    {
        $context = $this->scenario();
        $foreign = MallaCurricular::query()->create([
            'nombre' => 'Malla ajena', 'tipo' => 'institucional', 'carrera_id' => $context['otherCareer']->id,
            'creador_id' => $context['otherCoordinator']->id, 'activa' => true,
        ]);

        $this->postJson('/api/v1/coordinator/curricula', [
            'nombre' => 'No autorizada', 'tipo' => 'institucional', 'carrera_id' => $context['otherCareer']->id,
        ])->assertForbidden();
        $this->patchJson('/api/v1/coordinator/curricula/'.$foreign->id, ['activa' => false])->assertNotFound();

        $this->assertTrue($foreign->fresh()->activa);
    }

    public function test_comparisons_validate_percentage_origin_destination_and_career_scope(): void
    {
        $context = $this->scenario();
        $this->setState($context['solicitud'], 'en_proceso', $context['coordinator']);
        $originCurriculum = MallaCurricular::query()->create([
            'nombre' => 'Origen', 'tipo' => 'origen', 'carrera_id' => $context['career']->id,
            'creador_id' => $context['coordinator']->id, 'estudiante_id' => $context['student']->id,
        ]);
        $destinationCurriculum = MallaCurricular::query()->create([
            'nombre' => 'Destino', 'tipo' => 'institucional', 'carrera_id' => $context['career']->id,
            'creador_id' => $context['coordinator']->id,
        ]);
        $foreignCurriculum = MallaCurricular::query()->create([
            'nombre' => 'Destino ajeno', 'tipo' => 'institucional', 'carrera_id' => $context['otherCareer']->id,
            'creador_id' => $context['otherCoordinator']->id,
        ]);
        $origin = $this->subject($originCurriculum, 'ORI-1');
        $destination = $this->subject($destinationCurriculum, 'DES-1');
        $foreign = $this->subject($foreignCurriculum, 'EXT-1');
        $url = '/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/comparisons';

        foreach ([-0.01, 100.01] as $percentage) {
            $this->postJson($url, [
                'asignatura_origen_id' => $origin->id, 'asignatura_destino_id' => $destination->id,
                'porcentaje_coincidencia' => $percentage,
            ])->assertUnprocessable()->assertJsonValidationErrors('porcentaje_coincidencia');
        }
        $this->postJson($url, [
            'asignatura_origen_id' => $origin->id, 'asignatura_destino_id' => $foreign->id,
            'porcentaje_coincidencia' => 50,
        ])->assertForbidden();
        $this->postJson($url, [
            'asignatura_origen_id' => $origin->id, 'asignatura_destino_id' => $destination->id,
            'porcentaje_coincidencia' => 82.5, 'observacion' => 'Coincidencia suficiente.',
        ])->assertCreated()->assertJsonPath('data.porcentaje_coincidencia', '82.50');

        $this->assertDatabaseHas('comparaciones_asignatura', ['solicitud_id' => $context['solicitud']->id, 'porcentaje_coincidencia' => 82.5]);
    }

    private function subject(MallaCurricular $curriculum, string $code): AsignaturaCredito
    {
        return $curriculum->asignaturas()->create([
            'codigo_asignatura' => $code, 'nombre_asignatura' => $code,
            'numero_creditos' => 4, 'nivel_ciclo' => 'primero', 'hr_carga_horaria' => 64,
        ]);
    }
}
