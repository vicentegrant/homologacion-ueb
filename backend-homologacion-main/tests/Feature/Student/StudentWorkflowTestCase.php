<?php

namespace Tests\Feature\Student;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\EstadoSolicitud;
use App\Models\Solicitud;
use App\Models\TramiteProceso;
use App\Models\User;
use App\Services\StudentSolicitudService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class StudentWorkflowTestCase extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{student: User, coordinator: User, career: Carrera, assignment: CoordinadorCarrera, process: TramiteProceso, requirement: DocumentoRequeridoProceso} */
    protected function scenario(): array
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');
        $career = Carrera::query()->create(['nombre' => 'Software']);
        $assignment = CoordinadorCarrera::query()->create(['coordinador_id' => $coordinator->id, 'carrera_id' => $career->id]);
        $assignment->estudiantes()->create(['estudiante_id' => $student->id]);
        $process = TramiteProceso::query()->firstOrFail();
        $requirement = DocumentoRequeridoProceso::query()->create([
            'tramite_proceso_id' => $process->id, 'carrera_id' => $career->id,
            'nombre_documento' => 'Certificado de notas', 'ruta_ejemplo' => 'private/example.pdf',
        ]);
        $student->antecedentesAcademicos()->create([
            'universidad_origen' => 'Universidad de origen', 'carrera_origen' => 'Sistemas',
            'tipo_institucion' => 'publica', 'periodo_cursado' => '2025',
        ]);
        $this->asUser($student);

        return compact('student', 'coordinator', 'career', 'assignment', 'process', 'requirement');
    }

    protected function asUser(User $user): void
    {
        Auth::forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function payload(array $context): array
    {
        return ['coordinador_carrera_id' => $context['assignment']->id,
            'tramite_proceso_id' => $context['process']->id, 'procedencia_estudios' => 'Universidad de origen'];
    }

    /** @param array<string, mixed> $context */
    protected function draft(array $context): Solicitud
    {
        return app(StudentSolicitudService::class)->create($context['student'], $this->payload($context));
    }

    protected function state(Solicitud $solicitud, string $state): void
    {
        $solicitud->historialEstados()->create(['estado_solicitud_id' => EstadoSolicitud::query()->where('nombre', $state)->firstOrFail()->id]);
    }

    protected function upload(Solicitud $solicitud): void
    {
        $document = $solicitud->documentos()->firstOrFail();
        $this->post("/api/v1/student/solicitudes/{$solicitud->id}/documentos/{$document->id}", [
            'archivo' => UploadedFile::fake()->createWithContent('notas.pdf', "%PDF-1.4\nnotas"),
        ], ['Accept' => 'application/json'])->assertOk();
    }
}
