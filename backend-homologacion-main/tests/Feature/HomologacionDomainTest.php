<?php

namespace Tests\Feature;

use App\Models\AntecedenteAcademico;
use App\Models\AsignaturaCredito;
use App\Models\Carrera;
use App\Models\ComparacionAsignatura;
use App\Models\CoordinadorCarrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\EstadoDocumento;
use App\Models\EstadoSolicitud;
use App\Models\HistorialEstadoSolicitud;
use App\Models\MallaCurricular;
use App\Models\ObservacionDocumentacion;
use App\Models\OficioSolicitud;
use App\Models\ResolucionSolicitud;
use App\Models\ResultadoSolicitud;
use App\Models\Solicitud;
use App\Models\SolicitudDocumento;
use App\Models\TemaSilaboAsignatura;
use App\Models\TipoProceso;
use App\Models\TipoTramite;
use App\Models\TramiteProceso;
use App\Models\User;
use App\Models\VerificacionDocumento;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomologacionDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_seeders_are_idempotent_and_create_all_expected_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, TipoTramite::query()->count());
        $this->assertSame(3, TipoProceso::query()->count());
        $this->assertSame(4, EstadoDocumento::query()->count());
        $this->assertSame(8, EstadoSolicitud::query()->count());
        $this->assertSame(3, TramiteProceso::query()->count());
        $this->assertDatabaseHas('roles', ['nombre' => 'administrador']);
        $this->assertDatabaseHas('roles', ['nombre' => 'coordinador']);
        $this->assertDatabaseHas('roles', ['nombre' => 'estudiante']);
    }

    public function test_initial_administrator_is_created_with_required_profile_and_role(): void
    {
        config()->set('app.initial_admin', [
            'name' => 'Administrador del sistema',
            'cedula' => '0900000001',
            'email' => 'admin@example.com',
            'phone' => '0990000001',
            'password' => 'password123',
        ]);
        $this->seed(RoleSeeder::class);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('Administrador del sistema', $admin->nombres_completos);
        $this->assertSame('0900000001', $admin->cedula);
        $this->assertSame('0990000001', $admin->numero_celular);
        $this->assertTrue($admin->hasRole('Administrador'));
    }

    public function test_complete_homologacion_record_exposes_its_relationships_and_casts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $estudiante = User::factory()->create();
        $coordinador = User::factory()->create();
        $carrera = Carrera::query()->create(['nombre' => 'Ingeniería de Software']);
        $tramiteProceso = TramiteProceso::query()->firstOrFail();

        AntecedenteAcademico::query()->create([
            'estudiante_id' => $estudiante->id,
            'universidad_origen' => 'Universidad de origen',
            'carrera_origen' => 'Sistemas',
            'tipo_institucion' => 'publica',
            'periodo_cursado' => '2024-2025',
        ]);
        $coordinacion = CoordinadorCarrera::query()->create([
            'coordinador_id' => $coordinador->id,
            'carrera_id' => $carrera->id,
        ]);
        $coordinacion->estudiantes()->create(['estudiante_id' => $estudiante->id]);
        $solicitud = Solicitud::query()->create([
            'estudiante_id' => $estudiante->id,
            'coordinador_id' => $coordinador->id,
            'tramite_proceso_id' => $tramiteProceso->id,
            'procedencia_estudios' => 'Universidad de origen',
        ]);
        HistorialEstadoSolicitud::query()->create([
            'solicitud_id' => $solicitud->id,
            'estado_solicitud_id' => EstadoSolicitud::query()->where('nombre', 'pendiente')->firstOrFail()->id,
        ]);
        $oficio = OficioSolicitud::query()->create([
            'solicitud_id' => $solicitud->id,
            'numero_oficio' => 'OF-001',
            'fecha_oficio' => '2026-09-22',
            'ruta_oficio' => 'oficios/of-001.pdf',
        ]);
        $requisito = DocumentoRequeridoProceso::query()->create([
            'tramite_proceso_id' => $tramiteProceso->id,
            'carrera_id' => $carrera->id,
            'nombre_documento' => 'Certificado de notas',
        ]);
        $documento = SolicitudDocumento::query()->create([
            'solicitud_id' => $solicitud->id,
            'documento_requerido_proceso_id' => $requisito->id,
            'estado_documento_id' => EstadoDocumento::query()->where('nombre', 'presentado')->firstOrFail()->id,
            'ruta_documento_oficio' => 'documentos/notas.pdf',
            'validez' => true,
        ]);
        ObservacionDocumentacion::query()->create([
            'solicitud_documento_id' => $documento->id,
            'observacion' => 'Documento legible.',
        ]);
        VerificacionDocumento::query()->create([
            'solicitud_documento_id' => $documento->id,
            'coordinador_id' => $coordinador->id,
            'estado' => true,
        ]);
        $mallaOrigen = MallaCurricular::query()->create([
            'nombre' => 'Malla origen 2024',
            'tipo' => 'origen',
            'creador_id' => $coordinador->id,
            'estudiante_id' => $estudiante->id,
        ]);
        $mallaDestino = MallaCurricular::query()->create([
            'nombre' => 'Malla institucional 2026',
            'tipo' => 'institucional',
            'carrera_id' => $carrera->id,
            'creador_id' => $coordinador->id,
        ]);
        $asignaturaOrigen = AsignaturaCredito::query()->create([
            'malla_curricular_id' => $mallaOrigen->id,
            'codigo_asignatura' => 'ORI-101',
            'nombre_asignatura' => 'Programación I',
            'numero_creditos' => 4,
            'nivel_ciclo' => 'primero',
            'hr_carga_horaria' => 64,
        ]);
        $asignaturaDestino = AsignaturaCredito::query()->create([
            'malla_curricular_id' => $mallaDestino->id,
            'codigo_asignatura' => 'DES-101',
            'nombre_asignatura' => 'Fundamentos de Programación',
            'numero_creditos' => 4,
            'nivel_ciclo' => 'primero',
            'hr_carga_horaria' => 64,
        ]);
        TemaSilaboAsignatura::query()->create([
            'asignatura_id' => $asignaturaOrigen->id,
            'tema' => 'Estructuras de control',
            'unidad_analitica' => 'Unidad 1',
        ]);
        $comparacion = ComparacionAsignatura::query()->create([
            'solicitud_id' => $solicitud->id,
            'asignatura_origen_id' => $asignaturaOrigen->id,
            'asignatura_destino_id' => $asignaturaDestino->id,
            'porcentaje_coincidencia' => 85.5,
        ]);
        ResultadoSolicitud::query()->create([
            'solicitud_id' => $solicitud->id,
            'coordinador_id' => $coordinador->id,
            'conclusion_general' => 'parcial',
            'total_creditos_reconocidos' => 4,
        ]);
        $resolucion = ResolucionSolicitud::query()->create([
            'solicitud_id' => $solicitud->id,
            'coordinador_id' => $coordinador->id,
            'numero_resolucion' => 'RES-001',
            'fecha_aprobacion' => '2026-09-22',
            'ruta_archivo' => 'resoluciones/res-001.pdf',
        ]);

        $this->assertTrue($documento->validez);
        $this->assertSame('2026-09-22', $oficio->fecha_oficio->toDateString());
        $this->assertSame('85.50', $comparacion->porcentaje_coincidencia);
        $this->assertSame($estudiante->id, $solicitud->estudiante->id);
        $this->assertSame($coordinador->id, $solicitud->coordinador->id);
        $this->assertSame('presentado', $documento->estadoDocumento->nombre);
        $this->assertSame('2026-09-22', $resolucion->fecha_aprobacion->toDateString());
        $this->assertSame(1, $solicitud->historialEstados()->count());
        $this->assertSame(1, $solicitud->documentos()->count());
        $this->assertSame(1, $solicitud->comparacionesAsignaturas()->count());

        $solicitud->delete();

        $this->assertDatabaseMissing('solicitud_documentos', ['id' => $documento->id]);
        $this->assertDatabaseMissing('comparaciones_asignatura', ['id' => $comparacion->id]);
        $this->assertDatabaseMissing('resoluciones_solicitud', ['id' => $resolucion->id]);
    }
}
