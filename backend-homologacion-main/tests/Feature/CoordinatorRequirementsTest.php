<?php

namespace Tests\Feature;

use App\Models\DocumentoRequeridoProceso;
use App\Models\TramiteProceso;
use App\Models\User;
use Tests\Feature\Coordinator\CoordinatorWorkflowTestCase;

class CoordinatorRequirementsTest extends CoordinatorWorkflowTestCase
{
    public function test_coordinator_configures_requirements_independently_for_all_three_procedures(): void
    {
        $context = $this->scenario();
        $processes = TramiteProceso::query()->orderBy('id')->get();
        $this->assertCount(3, $processes);
        foreach ($processes as $process) {
            $this->postJson('/api/v1/coordinator/requirements', $this->payload($context, ['tramite_proceso_id' => $process->id]))
                ->assertCreated()->assertJsonPath('data.carrera_id', $context['career']->id)
                ->assertJsonPath('data.tramite_proceso_id', $process->id);
        }
        $this->getJson('/api/v1/coordinator/requirements')->assertOk()->assertJsonCount(3, 'data.tramites');
        $this->assertSame(3, DocumentoRequeridoProceso::query()->where('carrera_id', $context['career']->id)->where('nombre_documento', 'Certificado de prueba')->count());
    }

    public function test_other_careers_and_global_requirements_cannot_be_modified(): void
    {
        $context = $this->scenario();
        $other = DocumentoRequeridoProceso::query()->create([
            'nombre_documento' => 'Documento ajeno', 'carrera_id' => $context['otherCareer']->id,
            'tramite_proceso_id' => $context['solicitud']->tramite_proceso_id,
        ]);
        $global = DocumentoRequeridoProceso::query()->create([
            'nombre_documento' => 'Documento institucional', 'tramite_proceso_id' => $context['solicitud']->tramite_proceso_id,
        ]);
        $this->postJson('/api/v1/coordinator/requirements', $this->payload($context, ['carrera_id' => $context['otherCareer']->id]))->assertForbidden();
        foreach ([$other, $global] as $record) {
            $this->putJson('/api/v1/coordinator/requirements/'.$record->id, $this->payload($context))->assertNotFound();
            $this->deleteJson('/api/v1/coordinator/requirements/'.$record->id)->assertNotFound();
            $this->assertModelExists($record);
        }
        $response = $this->getJson('/api/v1/coordinator/requirements')->assertOk();
        $this->assertNotContains($other->id, array_column($response->json('data.requisitos'), 'id'));
        $this->assertContains($global->id, array_column($response->json('data.generales'), 'id'));
    }

    public function test_repeated_names_and_invalid_fields_are_rejected(): void
    {
        $context = $this->scenario();
        $payload = $this->payload($context);
        $id = $this->postJson('/api/v1/coordinator/requirements', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/coordinator/requirements', [...$payload, 'nombre' => '  CERTIFICADO DE PRUEBA  '])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->putJson('/api/v1/coordinator/requirements/'.$id, [...$payload, 'descripcion' => 'Indicaciones actualizadas'])
            ->assertOk()->assertJsonPath('data.descripcion', 'Indicaciones actualizadas');
        DocumentoRequeridoProceso::query()->create([
            'nombre_documento' => 'Documento general repetido', 'tramite_proceso_id' => $payload['tramite_proceso_id'],
        ]);
        $this->postJson('/api/v1/coordinator/requirements', [...$payload, 'nombre' => 'Documento general repetido'])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->postJson('/api/v1/coordinator/requirements', [...$payload, 'nombre' => '', 'carrera_id' => null,
            'tramite_proceso_id' => 999999, 'descripcion' => str_repeat('x', 2001), 'obligatorio' => 'incorrecto',
        ])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'carrera_id', 'tramite_proceso_id', 'descripcion', 'obligatorio']);
    }

    public function test_unused_requirement_can_be_deleted(): void
    {
        $context = $this->scenario();
        $id = $this->postJson('/api/v1/coordinator/requirements', $this->payload($context))->assertCreated()->json('data.id');
        $this->deleteJson('/api/v1/coordinator/requirements/'.$id)->assertOk();
        $this->assertDatabaseMissing('documentos_requeridos_proceso', ['id' => $id]);
    }

    public function test_student_preview_and_new_request_use_requirements_while_old_checklist_is_preserved(): void
    {
        $context = $this->scenario();
        $payload = $this->payload($context);
        $id = $this->postJson('/api/v1/coordinator/requirements', $payload)->assertCreated()->json('data.id');
        $other = DocumentoRequeridoProceso::query()->create([
            'carrera_id' => $context['otherCareer']->id, 'tramite_proceso_id' => $payload['tramite_proceso_id'], 'nombre_documento' => 'No mostrar',
        ]);
        $student = $this->student($context);
        $this->asUser($student);
        $catalog = $this->getJson('/api/v1/student/catalogo')->assertOk()->assertJsonPath('data.procedencia_configurada', true)
            ->assertJsonPath('data.procedencia_estudios', 'Universidad de Cuenca');
        $this->assertContains($id, array_column($catalog->json('data.requisitos'), 'id'));
        $this->assertNotContains($other->id, array_column($catalog->json('data.requisitos'), 'id'));
        $requestId = $this->postJson('/api/v1/student/solicitudes', [
            'coordinador_carrera_id' => $context['assignment']->id, 'tramite_proceso_id' => $payload['tramite_proceso_id'],
        ])->assertCreated()->assertJsonPath('data.procedencia_estudios', 'Universidad de Cuenca')
            ->assertJsonPath('data.puede_editar_procedencia', false)->json('data.id');
        $this->patchJson('/api/v1/student/solicitudes/'.$requestId, ['procedencia_estudios' => 'Otra institución'])
            ->assertUnprocessable()->assertJsonValidationErrors('procedencia_estudios');
        $this->assertDatabaseHas('solicitudes', ['id' => $requestId, 'procedencia_estudios' => 'Universidad de Cuenca']);
        $this->asUser($context['coordinator']);
        $this->putJson('/api/v1/coordinator/requirements/'.$id, [...$payload, 'nombre' => 'Nuevo nombre', 'descripcion' => 'Nueva indicación', 'obligatorio' => false, 'activa' => false])->assertOk();
        $this->assertDatabaseHas('solicitud_documentos', [
            'solicitud_id' => $requestId, 'documento_requerido_proceso_id' => $id,
            'requisito_nombre' => 'Certificado de prueba', 'requisito_descripcion' => 'Presentar copia certificada.', 'obligatorio' => true,
        ]);
        $this->deleteJson('/api/v1/coordinator/requirements/'.$id)->assertStatus(409);
        $this->assertDatabaseHas('documentos_requeridos_proceso', ['id' => $id, 'activo' => false]);
        $this->asUser($student);
        $catalog = $this->getJson('/api/v1/student/catalogo')->assertOk();
        $this->assertNotContains($id, array_column($catalog->json('data.requisitos'), 'id'));
    }

    public function test_used_requirements_cannot_be_moved_to_another_procedure(): void
    {
        $context = $this->scenario();
        $requirement = $context['solicitud']->documentos()->firstOrFail()->documentoRequerido;
        $other = TramiteProceso::query()->whereKeyNot($requirement->tramite_proceso_id)->firstOrFail();
        $this->putJson('/api/v1/coordinator/requirements/'.$requirement->id, $this->payload($context, ['tramite_proceso_id' => $other->id]))
            ->assertStatus(409);
        $this->assertDatabaseHas('documentos_requeridos_proceso', ['id' => $requirement->id, 'tramite_proceso_id' => $context['solicitud']->tramite_proceso_id]);
    }

    public function test_requirement_routes_require_authentication_and_coordinator_role(): void
    {
        $this->getJson('/api/v1/coordinator/requirements')->assertUnauthorized();
        $context = $this->scenario();
        foreach (['administrador', 'estudiante'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->asUser($user);
            $this->getJson('/api/v1/coordinator/requirements')->assertForbidden();
            $this->postJson('/api/v1/coordinator/requirements', $this->payload($context))->assertForbidden();
        }
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $context, array $overrides = []): array
    {
        return [...[
            'nombre' => 'Certificado de prueba', 'descripcion' => 'Presentar copia certificada.',
            'carrera_id' => $context['career']->id, 'tramite_proceso_id' => $context['solicitud']->tramite_proceso_id,
            'activa' => true, 'obligatorio' => true,
        ], ...$overrides];
    }

    /** @param array<string, mixed> $context */
    private function student(array $context): User
    {
        $student = User::factory()->create();
        $student->assignRole('estudiante');
        $context['assignment']->estudiantes()->create(['estudiante_id' => $student->id]);
        $student->antecedentesAcademicos()->create([
            'procedencia' => 'externa', 'universidad_origen' => 'Universidad de Cuenca', 'carrera_origen' => 'Software',
            'tipo_institucion' => 'publica', 'periodo_cursado' => 'Primer ciclo / semestre',
        ]);

        return $student;
    }
}
