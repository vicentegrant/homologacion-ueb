<?php

namespace Tests\Feature\Coordinator;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\EstadoDocumento;
use App\Models\EstadoSolicitud;
use App\Models\Solicitud;
use App\Models\TramiteProceso;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class CoordinatorWorkflowTestCase extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    protected function scenario(string $state = 'en_revision'): array
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinador');
        $otherCoordinator = User::factory()->create();
        $otherCoordinator->assignRole('Coordinador');
        $student = User::factory()->create();
        $student->assignRole('Estudiante');
        $otherStudent = User::factory()->create();
        $otherStudent->assignRole('Estudiante');
        $career = Carrera::query()->create(['nombre' => 'Software']);
        $otherCareer = Carrera::query()->create(['nombre' => 'Industrial']);
        $assignment = CoordinadorCarrera::query()->create(['coordinador_id' => $coordinator->id, 'carrera_id' => $career->id]);
        $otherAssignment = CoordinadorCarrera::query()->create(['coordinador_id' => $otherCoordinator->id, 'carrera_id' => $otherCareer->id]);
        $assignment->estudiantes()->create(['estudiante_id' => $student->id]);
        $otherAssignment->estudiantes()->create(['estudiante_id' => $otherStudent->id]);
        $solicitud = $this->solicitud($student, $coordinator, $career, $state);
        $otherSolicitud = $this->solicitud($otherStudent, $otherCoordinator, $otherCareer, $state);
        $this->asUser($coordinator);

        return compact('coordinator', 'otherCoordinator', 'student', 'otherStudent', 'career', 'otherCareer', 'assignment', 'otherAssignment', 'solicitud', 'otherSolicitud');
    }

    protected function asUser(User $user): void
    {
        Auth::forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    protected function setState(Solicitud $solicitud, string $state, ?User $actor = null): void
    {
        $solicitud->historialEstados()->create([
            'estado_solicitud_id' => EstadoSolicitud::query()->where('nombre', $state)->firstOrFail()->id,
            'usuario_responsable_id' => $actor?->id,
        ]);
    }

    private function solicitud(User $student, User $coordinator, Carrera $career, string $state): Solicitud
    {
        $process = TramiteProceso::query()->firstOrFail();
        $requirement = DocumentoRequeridoProceso::query()->create([
            'tramite_proceso_id' => $process->id,
            'carrera_id' => $career->id,
            'nombre_documento' => 'Certificado '.$career->id,
        ]);
        $solicitud = Solicitud::query()->create([
            'estudiante_id' => $student->id,
            'coordinador_id' => $coordinator->id,
            'carrera_id' => $career->id,
            'tramite_proceso_id' => $process->id,
            'procedencia_estudios' => 'Universidad de origen',
        ]);
        $document = $solicitud->documentos()->create([
            'documento_requerido_proceso_id' => $requirement->id,
            'estado_documento_id' => EstadoDocumento::query()->where('nombre', 'presentado')->firstOrFail()->id,
            'ruta_documento_oficio' => 'solicitudes/'.$solicitud->id.'/documento.pdf',
        ]);
        Storage::disk('local')->put($document->ruta_documento_oficio, "%PDF-1.4\ncontenido");
        $this->setState($solicitud, $state, $student);

        return $solicitud;
    }
}
