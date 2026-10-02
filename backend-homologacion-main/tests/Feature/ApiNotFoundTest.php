<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApiNotFoundTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_missing_model_returns_the_api_404_contract_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/users/999999')
            ->assertNotFound()->assertExactJson([
                'success' => false,
                'message' => 'Recurso no encontrado.',
            ]);
    }

    public function test_unknown_api_route_returns_json_without_an_accept_header(): void
    {
        $this->get('/api/v1/does-not-exist')->assertNotFound()->assertExactJson([
            'success' => false,
            'message' => 'Recurso no encontrado.',
        ]);
    }

    public function test_unknown_web_route_retains_an_html_response(): void
    {
        $this->get('/does-not-exist')->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
