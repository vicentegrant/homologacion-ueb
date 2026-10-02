<?php

namespace Tests\Feature\Coordinator;

use App\Models\MallaCurricular;
use Illuminate\Support\Facades\Storage;

class TechnicalReportTest extends CoordinatorWorkflowTestCase
{
    public function test_report_contains_all_pages_long_observations_and_spanish_characters(): void
    {
        $context = $this->scenario('aprobado');
        $solicitud = $context['solicitud'];
        $context['student']->update(['nombres_completos' => 'María Muñoz']);
        $solicitud->resultado()->create([
            'coordinador_id' => $context['coordinator']->id,
            'conclusion_general' => 'total', 'total_creditos_reconocidos' => 4,
        ]);
        $curriculum = MallaCurricular::query()->create([
            'nombre' => 'Malla', 'tipo' => 'institucional', 'carrera_id' => $context['career']->id,
            'creador_id' => $context['coordinator']->id,
        ]);
        $attributes = ['nombre_asignatura' => 'Asignatura', 'numero_creditos' => 4, 'nivel_ciclo' => 'primero', 'hr_carga_horaria' => 64];
        $origin = $curriculum->asignaturas()->create([...$attributes, 'codigo_asignatura' => 'ORI']);
        $destination = $curriculum->asignaturas()->create([...$attributes, 'codigo_asignatura' => 'DES']);
        $solicitud->comparacionesAsignaturas()->create([
            'asignatura_origen_id' => $origin->id, 'asignatura_destino_id' => $destination->id,
            'porcentaje_coincidencia' => 90,
            'observacion' => str_repeat("Contenido completo (álgebra) y revisión.\n", 90)
                .'INICIO-LARGO '.str_repeat('explicacion ', 30).'FIN-OBSERVACION',
        ]);
        $url = '/api/v1/coordinator/solicitudes/'.$solicitud->id.'/technical-report';
        $this->postJson($url)->assertCreated()->assertJsonPath('data.informe_tecnico_disponible', true);
        $result = $solicitud->fresh()->resultado;
        $this->assertNotNull($result->informe_generado_at);
        $pdf = $this->get($url, ['Accept' => 'application/json'])->assertOk()->assertHeader('content-type', 'application/pdf')->streamedContent();

        $this->assertStringContainsString('/Count 3', $pdf);
        $this->assertStringContainsString(mb_convert_encoding('María Muñoz', 'Windows-1252', 'UTF-8'), $pdf);
        $this->assertStringContainsString('FIN-OBSERVACION', $pdf);
        $this->assertStringContainsString('Creditos reconocidos: 4', $pdf);
        $this->assertStringContainsString('Pagina 3 de 3', $pdf);
        $this->assertSame(90, substr_count($pdf, 'Contenido completo'));
        $this->assertSame(30, substr_count($pdf, 'explicacion'));
        Storage::disk('local')->assertExists($result->ruta_informe_tecnico);

        $this->setState($solicitud, 'en_consejo', $context['coordinator']);
        $context['student']->update(['nombres_completos' => 'Nombre cambiado posteriormente']);
        $this->postJson($url)->assertCreated();
        $this->assertSame($pdf, Storage::disk('local')->get($result->ruta_informe_tecnico));
        $this->assertSame($result->informe_generado_at->toISOString(), $result->fresh()->informe_generado_at->toISOString());
    }

    public function test_returns_409_without_recreating_a_missing_report_after_council_submission(): void
    {
        $context = $this->scenario('en_consejo');
        $context['solicitud']->resultado()->create([
            'coordinador_id' => $context['coordinator']->id,
            'conclusion_general' => 'parcial', 'total_creditos_reconocidos' => 2,
            'ruta_informe_tecnico' => 'informes-tecnicos/ausente.pdf',
        ]);

        $this->postJson('/api/v1/coordinator/solicitudes/'.$context['solicitud']->id.'/technical-report')->assertStatus(409);

        Storage::disk('local')->assertMissing('informes-tecnicos/ausente.pdf');
        $this->assertSame('en_consejo', $context['solicitud']->fresh()->ultimoHistorialEstado->estadoSolicitud->nombre);
    }
}
