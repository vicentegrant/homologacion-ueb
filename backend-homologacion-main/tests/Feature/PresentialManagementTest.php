<?php

namespace Tests\Feature;

use App\Models\EstadoDocumento;
use App\Models\Modalidad;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Coordinator\CoordinatorWorkflowTestCase;

class PresentialManagementTest extends CoordinatorWorkflowTestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $this->asUser($user);

        return $user;
    }

    public function test_administrator_can_create_read_update_and_delete_catalogs_with_reference_protection(): void
    {
        $c = $this->scenario();
        $this->admin();
        $faculty = $this->postJson('/api/v1/admin/catalogs/facultades', ['nombre' => 'Facultad nueva', 'activa' => true])->assertCreated()->json('data.id');
        $modality = Modalidad::firstOrFail();
        $career = $this->postJson('/api/v1/admin/catalogs/carreras', ['nombre' => 'Carrera nueva', 'activa' => true, 'facultad_id' => $faculty, 'modalidad_ids' => [$modality->id]])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/admin/catalogs')->assertOk()->assertJsonFragment(['nombre' => 'Carrera nueva']);
        $this->putJson('/api/v1/admin/catalogs/facultades/'.$faculty, ['nombre' => 'Facultad editada', 'activa' => true])->assertOk()->assertJsonPath('data.nombre', 'Facultad editada');
        $this->deleteJson('/api/v1/admin/catalogs/facultades/'.$faculty)->assertStatus(409);
        $this->deleteJson('/api/v1/admin/catalogs/modalidades/'.$modality->id)->assertStatus(409);
        $this->deleteJson('/api/v1/admin/catalogs/carreras/'.$career)->assertOk();
        $this->deleteJson('/api/v1/admin/catalogs/facultades/'.$faculty)->assertOk();
        $this->assertDatabaseMissing('facultades', ['id' => $faculty]);
        $this->asUser($c['coordinator']);
        $this->getJson('/api/v1/admin/catalogs')->assertForbidden();
    }

    public function test_coordinator_creates_only_students_with_temporary_password_and_destination_in_own_scope(): void
    {
        Notification::fake();
        $c = $this->scenario();
        $modality = Modalidad::firstOrFail();
        $c['career']->modalidades()->attach($modality);
        $data = ['nombres_completos' => 'Estudiante Pasaporte', 'tipo_identificacion' => 'pasaporte', 'cedula' => 'AB12345', 'email' => 'passport@example.test', 'numero_celular' => '0991234567', 'carrera_id' => $c['career']->id, 'modalidad_id' => $modality->id];
        $this->postJson('/api/v1/coordinator/students', [...$data, 'rol_id' => Role::where('nombre', 'administrador')->firstOrFail()->id])->assertUnprocessable()->assertJsonValidationErrors('rol_id');
        $this->postJson('/api/v1/coordinator/students', [...$data, 'carrera_id' => $c['otherCareer']->id])->assertForbidden();
        $id = $this->postJson('/api/v1/coordinator/students', $data)->assertCreated()->assertJsonPath('data.tipo_identificacion', 'pasaporte')->json('data.id');
        $user = User::findOrFail($id);
        $this->assertTrue($user->hasRole('estudiante'));
        $this->assertTrue($user->must_change_password);
        Notification::assertSentTo($user, TemporaryPasswordNotification::class);
        $this->assertDatabaseHas('estudiante_carreras', ['estudiante_id' => $id, 'coordinador_carrera_id' => $c['assignment']->id, 'modalidad_id' => $modality->id]);
        $this->getJson('/api/v1/coordinator/students?tipo_identificacion=pasaporte&search=AB12345')->assertOk()->assertJsonPath('meta.total', 1);
        $this->putJson('/api/v1/coordinator/students/'.$id, [...$data, 'nombres_completos' => 'Nombre actualizado'])->assertOk()->assertJsonPath('data.nombres_completos', 'Nombre actualizado');
        $this->deleteJson('/api/v1/coordinator/students/'.$id)->assertOk();
        $this->assertFalse($user->refresh()->cuenta_activa);
        $this->patchJson('/api/v1/coordinator/students/'.$id.'/status', ['cuenta_activa' => true])->assertOk();
        $c['otherCareer']->modalidades()->attach($modality);
        $this->asUser($c['otherCoordinator']);
        $this->putJson('/api/v1/coordinator/students/'.$id, [...$data, 'carrera_id' => $c['otherCareer']->id])->assertNotFound();
    }

    public function test_identity_rules_depend_on_type_and_reject_duplicates_and_invalid_modalities(): void
    {
        $c = $this->scenario();
        $m = Modalidad::firstOrFail();
        $c['career']->modalidades()->attach($m);
        $base = ['nombres_completos' => 'Nuevo', 'tipo_identificacion' => 'cedula', 'cedula' => '123456789', 'email' => 'id@example.test', 'numero_celular' => '0999999999', 'carrera_id' => $c['career']->id, 'modalidad_id' => $m->id];
        $this->postJson('/api/v1/coordinator/students', $base)->assertUnprocessable()->assertJsonValidationErrors('cedula');
        $this->postJson('/api/v1/coordinator/students', [...$base, 'cedula' => 'ABCDEFGHIJ'])->assertUnprocessable()->assertJsonValidationErrors('cedula');
        $this->postJson('/api/v1/coordinator/students', [...$base, 'tipo_identificacion' => 'pasaporte', 'cedula' => 'AB 123'])->assertUnprocessable()->assertJsonValidationErrors('cedula');
        $this->postJson('/api/v1/coordinator/students', [...$base, 'cedula' => $c['student']->cedula])->assertUnprocessable()->assertJsonValidationErrors('cedula');
        $this->postJson('/api/v1/coordinator/students', [...$base, 'cedula' => '1234567890', 'modalidad_id' => Modalidad::where('id', '!=', $m->id)->firstOrFail()->id])->assertUnprocessable();
    }

    public function test_presential_checklist_progress_observations_corrections_and_automatic_transitions(): void
    {
        $c = $this->scenario('pendiente');
        $s = $c['solicitud'];
        $first = $s->documentos()->firstOrFail();
        $first->update(['recibido_at' => null, 'ruta_documento_oficio' => null, 'estado_documento_id' => EstadoDocumento::where('nombre', 'pendiente')->firstOrFail()->id]);
        $requirement = $first->documentoRequerido->replicate();
        $requirement->nombre_documento = 'Segundo';
        $requirement->save();
        $second = $first->replicate();
        $second->documento_requerido_proceso_id = $requirement->id;
        $second->save();
        $url = '/api/v1/coordinator/documents/'.$first->id.'/review';
        $this->patchJson($url, ['estado' => 'aprobado'])->assertStatus(409);
        $this->patchJson($url, ['estado' => 'presentado'])->assertOk()->assertJsonPath('data.presentado', true);
        $this->patchJson($url, ['estado' => 'aprobado'])->assertOk();
        $this->patchJson('/api/v1/coordinator/documents/'.$second->id.'/review', ['estado' => 'observado', 'observacion' => 'Falta sello.'])->assertOk();
        $this->asUser($c['student']);
        $this->getJson('/api/v1/student/solicitudes/'.$s->id)->assertOk()->assertJsonPath('data.progreso_documental', 50)->assertJsonPath('data.estado_actual', 'observado')->assertJsonPath('data.documentos.1.observaciones.0.observacion', 'Falta sello.');
        $this->postJson('/api/v1/student/solicitudes/'.$s->id.'/enviar')->assertStatus(410);
        $this->postJson('/api/v1/student/solicitudes/'.$s->id.'/documentos/'.$first->id)->assertStatus(410);
        $this->patchJson($url, ['estado' => 'aprobado'])->assertForbidden();
        $this->admin();
        $this->patchJson($url, ['estado' => 'aprobado'])->assertForbidden();
        $this->asUser($c['coordinator']);
        $this->patchJson('/api/v1/coordinator/documents/'.$second->id.'/review', ['estado' => 'aprobado'])->assertStatus(409);
        $this->patchJson('/api/v1/coordinator/documents/'.$second->id.'/review', ['estado' => 'presentado'])->assertOk();
        $this->assertSame('en_revision', $s->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->patchJson('/api/v1/coordinator/documents/'.$second->id.'/review', ['estado' => 'aprobado'])->assertOk();
        $this->assertSame('en_proceso', $s->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->asUser($c['student']);
        $this->getJson('/api/v1/student/solicitudes/'.$s->id)->assertOk()->assertJsonPath('data.progreso_documental', 100);
        $this->assertDatabaseCount('historial_documentos', 5);
        $this->assertNull($first->refresh()->ruta_documento_oficio);
    }

    public function test_requirement_in_use_is_not_deleted_and_edits_preserve_snapshot(): void
    {
        $c = $this->scenario();
        $d = $c['solicitud']->documentos()->firstOrFail();
        $d->update(['requisito_nombre' => 'Nombre original']);
        $this->admin();
        $r = $d->documentoRequerido;
        $this->putJson('/api/v1/admin/catalogs/requisitos/'.$r->id, ['nombre' => 'Nuevo nombre', 'activa' => false, 'obligatorio' => false, 'tramite_proceso_id' => $r->tramite_proceso_id, 'carrera_id' => $r->carrera_id, 'descripcion' => 'Nueva indicación'])->assertOk();
        $this->deleteJson('/api/v1/admin/catalogs/requisitos/'.$r->id)->assertStatus(409);
        $this->asUser($c['student']);
        $this->getJson('/api/v1/student/solicitudes/'.$c['solicitud']->id)->assertOk()->assertJsonPath('data.documentos.0.requisito.nombre', 'Nombre original')->assertJsonPath('data.documentos.0.obligatorio', true);
    }

    public function test_coordinator_cannot_disable_student_with_active_request_or_change_destination(): void
    {
        $c = $this->scenario();
        $this->deleteJson('/api/v1/coordinator/students/'.$c['student']->id)->assertStatus(409);
        $this->getJson('/api/v1/coordinator/students/'.$c['otherStudent']->id)->assertNotFound();
    }

    public function test_historical_uploaded_document_can_be_received_physically_and_validated(): void
    {
        $c = $this->scenario();
        $document = $c['solicitud']->documentos()->firstOrFail();
        $document->update(['recibido_at' => null, 'recibido_por_id' => null]);
        $url = '/api/v1/coordinator/documents/'.$document->id.'/review';
        $this->patchJson($url, ['estado' => 'presentado'])->assertOk();
        $this->assertNotNull($document->refresh()->recibido_at);
        $this->patchJson($url, ['estado' => 'aprobado'])->assertOk();
        $this->assertSame('en_proceso', $c['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
    }

    public function test_optional_observations_do_not_reduce_mandatory_progress_or_block_processing(): void
    {
        $c = $this->scenario();
        $document = $c['solicitud']->documentos()->firstOrFail();
        $r = $document->documentoRequerido->replicate();
        $r->nombre_documento = 'Complemento';
        $r->save();
        $optional = $document->replicate();
        $optional->documento_requerido_proceso_id = $r->id;
        $optional->obligatorio = false;
        $optional->save();
        $this->patchJson('/api/v1/coordinator/documents/'.$optional->id.'/review', ['estado' => 'observado', 'observacion' => 'Complemento pendiente.'])->assertOk();
        $this->patchJson('/api/v1/coordinator/documents/'.$document->id.'/review', ['estado' => 'aprobado'])->assertOk();
        $this->asUser($c['student']);
        $this->getJson('/api/v1/student/solicitudes/'.$c['solicitud']->id)->assertOk()->assertJsonPath('data.progreso_documental', 100)->assertJsonPath('data.estado_actual', 'en_proceso');
    }

    public function test_historical_access_does_not_grant_student_write_access_without_current_assignment(): void
    {
        $c = $this->scenario();
        $c['student']->carrerasComoEstudiante()->delete();
        $this->getJson('/api/v1/coordinator/students/'.$c['student']->id)->assertOk();
        $this->deleteJson('/api/v1/coordinator/students/'.$c['student']->id)->assertNotFound();
        $this->assertTrue($c['student']->refresh()->cuenta_activa);
    }
}
