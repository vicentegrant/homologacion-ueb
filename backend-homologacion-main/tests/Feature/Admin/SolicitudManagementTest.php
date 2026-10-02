<?php

namespace Tests\Feature\Admin;

use App\Models\EstadoSolicitud;
use App\Models\HistorialEstadoSolicitud;
use App\Models\ResolucionSolicitud;
use App\Models\Solicitud;
use App\Models\TramiteProceso;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SolicitudManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_paginate_and_filter_requests_by_current_state_and_student(): void
    {
        $this->authenticateAdministrator();
        $pending = $this->createSolicitud('pendiente', 'Ana Filtro');
        $this->createSolicitud('aprobado', 'Otra Persona');

        $this->getJson('/api/v1/admin/solicitudes?estado=pendiente&estudiante=Ana&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_administrator_can_view_structured_request_detail(): void
    {
        $this->authenticateAdministrator();
        $solicitud = $this->createSolicitud('en_revision', 'Detalle Estudiante');

        $this->getJson("/api/v1/admin/solicitudes/{$solicitud->id}")
            ->assertOk()
            ->assertJsonPath('data.estudiante.nombres_completos', 'Detalle Estudiante')
            ->assertJsonPath('data.estado_actual.nombre', 'en_revision')
            ->assertJsonStructure(['data' => ['tramite', 'documentos', 'historial_estados', 'resultado', 'resolucion']]);
    }

    public function test_administrator_can_register_and_securely_download_pdf_resolution(): void
    {
        Storage::fake('local');
        $admin = $this->authenticateAdministrator();
        $solicitud = $this->createSolicitud('en_consejo', 'Con Resolución');
        $pdf = UploadedFile::fake()->createWithContent('resolucion.pdf', "%PDF-1.4\ncontenido");

        $this->post("/api/v1/admin/solicitudes/{$solicitud->id}/resolucion", [
            'numero_resolucion' => 'RES-2026-001',
            'fecha_aprobacion' => '2026-09-22',
            'archivo' => $pdf,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.numero_resolucion', 'RES-2026-001')
            ->assertJsonMissingPath('data.ruta_archivo');

        $resolution = ResolucionSolicitud::query()->firstOrFail();
        $this->assertSame($admin->id, $resolution->coordinador_id);
        $this->assertSame('listo', $solicitud->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->assertSame($admin->id, $solicitud->fresh()->ultimoHistorialEstado->usuario_responsable_id);
        Storage::disk('local')->assertExists($resolution->ruta_archivo);

        $this->get("/api/v1/admin/solicitudes/{$solicitud->id}/resolucion/download", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_resolution_rejects_duplicate_number_and_non_pdf_file(): void
    {
        Storage::fake('local');
        $admin = $this->authenticateAdministrator();
        $first = $this->createSolicitud('aprobado', 'Primero');
        $second = $this->createSolicitud('aprobado', 'Segundo');
        ResolucionSolicitud::query()->create([
            'solicitud_id' => $first->id,
            'coordinador_id' => $admin->id,
            'numero_resolucion' => 'RES-DUPLICADA',
            'fecha_aprobacion' => '2026-09-22',
            'ruta_archivo' => 'resoluciones/existente.pdf',
        ]);

        $this->post("/api/v1/admin/solicitudes/{$second->id}/resolucion", [
            'numero_resolucion' => 'RES-DUPLICADA',
            'fecha_aprobacion' => '2026-09-22',
            'archivo' => UploadedFile::fake()->createWithContent('resolucion.pdf', "%PDF-1.4\ncontenido"),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('numero_resolucion');

        $this->post("/api/v1/admin/solicitudes/{$second->id}/resolucion", [
            'numero_resolucion' => 'RES-NUEVA',
            'fecha_aprobacion' => '2026-09-22',
            'archivo' => UploadedFile::fake()->create('archivo.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }

    public function test_returns_409_and_keeps_storage_empty_when_resolution_is_uploaded_before_council(): void
    {
        Storage::fake('local');
        $this->authenticateAdministrator();
        $solicitud = $this->createSolicitud('pendiente', 'Todavía pendiente');

        $this->post('/api/v1/admin/solicitudes/'.$solicitud->id.'/resolucion', [
            'numero_resolucion' => 'RES-PREMATURA', 'fecha_aprobacion' => '2026-09-23',
            'archivo' => UploadedFile::fake()->createWithContent('resolucion.pdf', "%PDF-1.4\ncontenido"),
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $this->assertDatabaseMissing('resoluciones_solicitud', ['solicitud_id' => $solicitud->id]);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('pendiente', $solicitud->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
    }

    public function test_non_administrator_cannot_register_or_download_resolution(): void
    {
        Storage::fake('local');
        $this->authenticateAdministrator();
        $solicitud = $this->createSolicitud('aprobado', 'Protegido');
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $this->withToken($student->createToken('student')->plainTextToken);

        $this->post("/api/v1/admin/solicitudes/{$solicitud->id}/resolucion", [
            'numero_resolucion' => 'RES-PROHIBIDA',
            'fecha_aprobacion' => '2026-09-22',
            'archivo' => UploadedFile::fake()->createWithContent('resolucion.pdf', "%PDF-1.4\ncontenido"),
        ], ['Accept' => 'application/json'])->assertForbidden();
        $this->getJson("/api/v1/admin/solicitudes/{$solicitud->id}/resolucion/download")->assertForbidden();
        $this->assertDatabaseMissing('resoluciones_solicitud', ['numero_resolucion' => 'RES-PROHIBIDA']);
    }

    private function authenticateAdministrator(): User
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        $this->withToken($admin->createToken('test')->plainTextToken);

        return $admin;
    }

    private function createSolicitud(string $state, string $studentName): Solicitud
    {
        $student = User::factory()->create(['nombres_completos' => $studentName]);
        $student->assignRole('Estudiante');
        $solicitud = Solicitud::query()->create([
            'estudiante_id' => $student->id,
            'tramite_proceso_id' => TramiteProceso::query()->firstOrFail()->id,
            'procedencia_estudios' => 'Universidad de origen',
        ]);
        HistorialEstadoSolicitud::query()->create([
            'solicitud_id' => $solicitud->id,
            'estado_solicitud_id' => EstadoSolicitud::query()->where('nombre', $state)->firstOrFail()->id,
        ]);

        return $solicitud;
    }
}
