<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\Role;
use App\Models\Solicitud;
use App\Models\TramiteProceso;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EndToEndHomologacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_http_flow_from_administration_to_student_and_coordinator(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->asUser($admin);

        $coordinatorId = $this->postJson('/api/v1/admin/users', $this->userPayload('coordinador', [
            'nombres_completos' => 'Coordinador Integral', 'cedula' => '0900000101',
            'email' => 'coordinador.integral@example.com',
        ]))->assertCreated()->json('data.id');
        $studentId = $this->postJson('/api/v1/admin/users', $this->userPayload('estudiante', [
            'nombres_completos' => 'Estudiante Integral', 'cedula' => '0900000102',
            'email' => 'estudiante.integral@example.com',
        ]))->assertCreated()->json('data.id');

        $career = Carrera::query()->create(['nombre' => 'Ingeniería Integral']);
        $this->putJson("/api/v1/admin/coordinators/{$coordinatorId}/careers", ['carrera_ids' => [$career->id]])->assertOk();
        $assignment = CoordinadorCarrera::query()->where('coordinador_id', $coordinatorId)->firstOrFail();
        $assignment->estudiantes()->create(['estudiante_id' => $studentId]);
        $process = TramiteProceso::query()->firstOrFail();
        DocumentoRequeridoProceso::query()->create([
            'tramite_proceso_id' => $process->id, 'carrera_id' => $career->id,
            'nombre_documento' => 'Certificado académico',
        ]);

        $student = User::query()->findOrFail($studentId);
        $this->asUser($student);
        $this->postJson('/api/v1/student/antecedentes', [
            'universidad_origen' => 'Universidad de origen', 'carrera_origen' => 'Tecnologías',
            'tipo_institucion' => 'pública', 'periodo_cursado' => '2024-2025',
        ])->assertCreated();
        $requestResponse = $this->postJson('/api/v1/student/solicitudes', [
            'coordinador_carrera_id' => $assignment->id, 'tramite_proceso_id' => $process->id,
            'procedencia_estudios' => 'Universidad de origen',
        ])->assertCreated();
        $solicitudId = $requestResponse->json('data.id');
        $documentId = $requestResponse->json('data.documentos.0.id');
        $this->post("/api/v1/student/solicitudes/{$solicitudId}/documentos/{$documentId}", [
            'archivo' => UploadedFile::fake()->createWithContent('certificado.pdf', "%PDF-1.4\ncertificado"),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/v1/student/solicitudes/{$solicitudId}/enviar")
            ->assertOk()->assertJsonPath('data.estado_actual', 'en_revision');

        $coordinator = User::query()->findOrFail($coordinatorId);
        $this->asUser($coordinator);
        $this->patchJson("/api/v1/coordinator/documents/{$documentId}/review", ['estado' => 'aprobado'])->assertOk();
        $this->postJson("/api/v1/coordinator/documents/{$documentId}/verification", ['estado' => true])->assertOk();
        $originCurriculumId = $this->postJson('/api/v1/coordinator/curricula', [
            'nombre' => 'Malla de origen integral', 'tipo' => 'origen',
            'carrera_id' => $career->id, 'estudiante_id' => $studentId,
        ])->assertCreated()->json('data.id');
        $destinationCurriculumId = $this->postJson('/api/v1/coordinator/curricula', [
            'nombre' => 'Malla institucional integral', 'tipo' => 'institucional',
            'carrera_id' => $career->id, 'activa' => true,
        ])->assertCreated()->json('data.id');
        $originSubjectId = $this->postJson("/api/v1/coordinator/curricula/{$originCurriculumId}/subjects", $this->subjectPayload('ORI-101'))
            ->assertCreated()->json('data.id');
        $destinationSubjectId = $this->postJson("/api/v1/coordinator/curricula/{$destinationCurriculumId}/subjects", $this->subjectPayload('DES-101'))
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/coordinator/solicitudes/{$solicitudId}/comparisons", [
            'asignatura_origen_id' => $originSubjectId, 'asignatura_destino_id' => $destinationSubjectId,
            'porcentaje_coincidencia' => 90,
        ])->assertCreated();
        $this->postJson("/api/v1/coordinator/solicitudes/{$solicitudId}/result", [
            'conclusion_general' => 'total', 'total_creditos_reconocidos' => 4,
        ])->assertCreated();
        $this->postJson("/api/v1/coordinator/solicitudes/{$solicitudId}/technical-report")->assertCreated();
        $this->postJson("/api/v1/coordinator/solicitudes/{$solicitudId}/state", [
            'estado' => 'en_consejo', 'observacion' => 'Expediente remitido al Consejo.',
        ])->assertOk();
        $this->post("/api/v1/coordinator/solicitudes/{$solicitudId}/resolution", [
            'numero_resolucion' => 'RES-E2E-001', 'fecha_aprobacion' => '2026-09-23',
            'archivo' => UploadedFile::fake()->createWithContent('resolucion.pdf', "%PDF-1.4\nresolucion"),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('listo', Solicitud::query()->findOrFail($solicitudId)->ultimoHistorialEstado->estadoSolicitud->nombre);
        $this->asUser($student);
        $this->get("/api/v1/student/solicitudes/{$solicitudId}/resolucion/download", ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /** @param array<string, string> $overrides
     * @return array<string, mixed>
     */
    private function userPayload(string $role, array $overrides): array
    {
        return [
            'nombres_completos' => 'Usuario Integral', 'cedula' => '0900000199',
            'email' => 'usuario.integral@example.com', 'numero_celular' => '0990000101',
            'password' => 'Password123!',
            'rol_id' => Role::query()->where('nombre', $role)->firstOrFail()->id,
            ...$overrides,
        ];
    }

    /** @return array<string, int|string> */
    private function subjectPayload(string $code): array
    {
        return [
            'codigo_asignatura' => $code, 'nombre_asignatura' => 'Materia integral',
            'numero_creditos' => 4, 'nivel_ciclo' => 'primero', 'hr_carga_horaria' => 64,
        ];
    }

    private function asUser(User $user): void
    {
        Auth::forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}
