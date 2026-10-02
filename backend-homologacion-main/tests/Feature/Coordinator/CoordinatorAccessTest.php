<?php

namespace Tests\Feature\Coordinator;

use App\Models\User;

class CoordinatorAccessTest extends CoordinatorWorkflowTestCase
{
    public function test_coordinator_routes_require_authentication_active_account_and_role(): void
    {
        $context = $this->scenario();
        $this->withToken('invalid')->getJson('/api/v1/coordinator/solicitudes')->assertUnauthorized();

        foreach (['Estudiante', 'Administrador'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->asUser($user);
            $this->getJson('/api/v1/coordinator/solicitudes')->assertForbidden();
        }

        $context['coordinator']->update(['cuenta_activa' => false]);
        $this->asUser($context['coordinator']);
        $this->getJson('/api/v1/coordinator/solicitudes')->assertForbidden();
    }

    public function test_coordinator_only_lists_and_reads_students_and_requests_from_assigned_careers(): void
    {
        $context = $this->scenario();

        $this->getJson('/api/v1/coordinator/students')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $context['student']->id);
        $this->getJson('/api/v1/coordinator/solicitudes')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $context['solicitud']->id);
        $this->getJson('/api/v1/coordinator/students/'.$context['otherStudent']->id)->assertNotFound();
        $this->getJson('/api/v1/coordinator/solicitudes/'.$context['otherSolicitud']->id)->assertNotFound();
    }

    public function test_catalog_me_filters_and_reports_never_expose_an_unassigned_career(): void
    {
        $context = $this->scenario();

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.carreras_coordinadas.0.id', $context['career']->id);
        $this->getJson('/api/v1/coordinator/catalogo')->assertOk()->assertJsonCount(1, 'data.carreras');
        $this->getJson('/api/v1/coordinator/reports/solicitudes')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.registros.0.id', $context['solicitud']->id);
        $this->getJson('/api/v1/coordinator/solicitudes?carrera='.$context['otherCareer']->id)->assertForbidden();

        $this->assertDatabaseCount('solicitudes', 2);
    }
}
