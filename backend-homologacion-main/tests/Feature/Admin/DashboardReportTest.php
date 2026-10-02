<?php

namespace Tests\Feature\Admin;

use App\Models\EstadoSolicitud;
use App\Models\HistorialEstadoSolicitud;
use App\Models\Solicitud;
use App\Models\TramiteProceso;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_returns_database_backed_user_and_request_statistics(): void
    {
        $admin = $this->authenticateAdministrator();
        $student = User::factory()->create(['cuenta_activa' => false]);
        $student->assignRole('Estudiante');
        $this->createSolicitud($student, 'pendiente');

        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();

        $response->assertJsonPath('data.usuarios.total', User::query()->count())
            ->assertJsonPath('data.usuarios.inactivos', 1)
            ->assertJsonPath('data.solicitudes.total', 1);
        $this->assertTrue($admin->cuenta_activa);
    }

    public function test_request_report_applies_filters_and_returns_consistent_aggregates_and_pagination(): void
    {
        $this->authenticateAdministrator();
        $matchingStudent = User::factory()->create(['nombres_completos' => 'Reporte Coincidente']);
        $matchingStudent->assignRole('Estudiante');
        $otherStudent = User::factory()->create(['nombres_completos' => 'Reporte Otro']);
        $otherStudent->assignRole('Estudiante');
        $matching = $this->createSolicitud($matchingStudent, 'aprobado');
        $this->createSolicitud($otherStudent, 'pendiente');

        $this->getJson('/api/v1/admin/reports/solicitudes?estado=aprobado&estudiante=Coincidente&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.registros.0.id', $matching->id)
            ->assertJsonPath('data.paginacion.total', 1)
            ->assertJsonPath('data.filtros_aplicados.estado', 'aprobado');
    }

    private function authenticateAdministrator(): User
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        $this->withToken($admin->createToken('test')->plainTextToken);

        return $admin;
    }

    private function createSolicitud(User $student, string $state): Solicitud
    {
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
