<?php

namespace Tests\Feature\Student;

use App\Models\ResolucionSolicitud;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class NotificationResolutionTest extends StudentWorkflowTestCase
{
    public function test_state_observation_and_resolution_events_create_private_database_notifications(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->state($solicitud, 'observado');
        $solicitud->documentos()->firstOrFail()->observaciones()->create(['observacion' => 'Falta firma.']);
        $this->resolution($solicitud, $context['coordinator']);

        $this->getJson('/api/v1/student/notificaciones?sin_leer=1&per_page=2')->assertOk()
            ->assertJsonPath('meta.total', 4)->assertJsonPath('sin_leer', 4)->assertJsonCount(2, 'data');
        $events = $context['student']->notifications()->get()->pluck('data.evento')->all();
        $this->assertContains('estado_actualizado', $events);
        $this->assertContains('documento_observado', $events);
        $this->assertContains('resolucion_registrada', $events);
        $this->assertSame(0, $context['coordinator']->notifications()->count());
    }

    public function test_notification_read_is_idempotent_and_other_students_cannot_read_it(): void
    {
        $context = $this->scenario();
        $this->draft($context);
        $notification = $context['student']->notifications()->firstOrFail();

        $this->patchJson("/api/v1/student/notificaciones/{$notification->id}/leer")->assertOk();
        $readAt = $notification->fresh()->read_at->toISOString();
        $this->patchJson("/api/v1/student/notificaciones/{$notification->id}/leer")->assertOk();
        $this->assertSame($readAt, $notification->fresh()->read_at->toISOString());
        $this->getJson('/api/v1/student/notificaciones?sin_leer=1')->assertOk()->assertJsonCount(0, 'data');

        $other = User::factory()->create();
        $other->assignRole('Estudiante');
        $this->asUser($other);
        $this->getJson('/api/v1/student/notificaciones')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/student/notificaciones/{$notification->id}/leer")->assertNotFound();
    }

    public function test_rolled_back_state_does_not_leave_a_notification(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);

        try {
            DB::transaction(function () use ($solicitud): void {
                $this->state($solicitud, 'en_revision');
                throw new RuntimeException('Rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback', $exception->getMessage());
        }

        $this->assertSame(1, $context['student']->notifications()->count());
        $this->assertSame(1, $solicitud->historialEstados()->count());
    }

    public function test_resolution_can_only_be_downloaded_by_owner_when_request_is_ready(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->resolution($solicitud, $context['coordinator']);

        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}/resolucion/download")->assertStatus(409);
        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}")->assertOk()->assertJsonPath('data.resolucion', null);
        $this->state($solicitud, 'listo');
        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}")->assertOk()
            ->assertJsonPath('data.resolucion.download_url', "/api/v1/student/solicitudes/{$solicitud->id}/resolucion/download")
            ->assertJsonMissingPath('data.resolucion.ruta_archivo');
        $this->get("/api/v1/student/solicitudes/{$solicitud->id}/resolucion/download")->assertOk()
            ->assertHeader('content-type', 'application/pdf')->assertStreamedContent("%PDF-1.4\nresolucion");

        $other = User::factory()->create();
        $other->assignRole('Estudiante');
        $this->asUser($other);
        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}/resolucion/download")->assertNotFound();
    }

    public function test_missing_resolution_file_returns_404(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $this->resolution($solicitud, $context['coordinator']);
        $this->state($solicitud, 'listo');
        Storage::disk('local')->delete('resoluciones/final.pdf');

        $this->getJson("/api/v1/student/solicitudes/{$solicitud->id}/resolucion/download")
            ->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Recurso no encontrado.']);
    }

    public function test_all_workflow_endpoints_require_authentication_active_account_and_student_role(): void
    {
        $context = $this->scenario();
        $solicitud = $this->draft($context);
        $document = $solicitud->documentos()->firstOrFail();
        $notification = $context['student']->notifications()->firstOrFail();
        $endpoints = [
            ['GET', '/api/v1/student/catalogo'],
            ['GET', '/api/v1/student/solicitudes'],
            ['POST', '/api/v1/student/solicitudes'],
            ['GET', "/api/v1/student/solicitudes/{$solicitud->id}"],
            ['PATCH', "/api/v1/student/solicitudes/{$solicitud->id}"],
            ['POST', "/api/v1/student/solicitudes/{$solicitud->id}/enviar"],
            ['POST', "/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}"],
            ['GET', "/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}/download"],
            ['GET', "/api/v1/student/solicitudes/{$solicitud->id}/resolucion/download"],
            ['GET', '/api/v1/student/notificaciones'],
            ['PATCH', "/api/v1/student/notificaciones/{$notification->id}/leer"],
        ];

        Auth::forgetGuards();
        $this->withHeader('Authorization', '');
        foreach ($endpoints as [$method, $url]) {
            $this->json($method, $url)->assertUnauthorized();
        }
        foreach (['Administrador', 'Coordinador', 'Estudiante'] as $role) {
            $user = User::factory()->create(['cuenta_activa' => $role !== 'Estudiante']);
            $user->assignRole($role);
            foreach ($endpoints as [$method, $url]) {
                $this->asUser($user);
                $this->json($method, $url)->assertForbidden();
            }
        }
        $this->assertDatabaseCount('solicitudes', 1);
        $this->assertNull($document->fresh()->ruta_documento_oficio);
    }

    private function resolution(Solicitud $solicitud, User $coordinator): void
    {
        Storage::disk('local')->put('resoluciones/final.pdf', "%PDF-1.4\nresolucion");
        ResolucionSolicitud::query()->create([
            'solicitud_id' => $solicitud->id, 'coordinador_id' => $coordinator->id,
            'numero_resolucion' => 'RES-001', 'fecha_aprobacion' => '2026-09-23', 'ruta_archivo' => 'resoluciones/final.pdf',
        ]);
    }
}
