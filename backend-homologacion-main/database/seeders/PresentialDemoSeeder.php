<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\CoordinadorCarrera;
use App\Models\DocumentoRequeridoProceso;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\TramiteProceso;
use App\Models\User;
use App\Services\CoordinatorDocumentService;
use App\Services\StudentSolicitudService;
use Illuminate\Database\Seeder;

class PresentialDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Este seeder solo se permite en local/testing.');
        }
        $this->call([RoleSeeder::class, TipoTramiteSeeder::class, TipoProcesoSeeder::class, EstadoDocumentoSeeder::class, EstadoSolicitudSeeder::class, TramiteProcesoSeeder::class]);
        $users = [];
        foreach (['administrador' => 'Ana Torres · Demo', 'coordinador' => 'Carlos Andrade · Demo', 'estudiante' => 'Daniela Pérez · Demo'] as $role => $name) {
            $index = count($users) + 1;
            $user = User::firstOrCreate(['email' => $role.'.demo@example.test'], ['nombres_completos' => $name, 'tipo_identificacion' => 'cedula', 'cedula' => '990000000'.$index, 'numero_celular' => '099000000'.$index, 'password' => 'DemoUeb2026!', 'cuenta_activa' => true]);
            $user->assignRole($role);
            $users[$role] = $user;
        }
        $faculty = Facultad::firstOrCreate(['nombre' => '[DEMO] Facultad de Ciencias Administrativas, Gestión Empresarial e Informática']);
        $career = Carrera::firstOrCreate(['nombre' => '[DEMO] Ingeniería de Software'], ['facultad_id' => $faculty->id]);
        $modality = Modalidad::where('nombre', 'Presencial')->firstOrFail();
        $career->modalidades()->syncWithoutDetaching([$modality->id]);
        $assignment = CoordinadorCarrera::firstOrCreate(['coordinador_id' => $users['coordinador']->id, 'carrera_id' => $career->id]);
        $assignment->estudiantes()->firstOrCreate(['estudiante_id' => $users['estudiante']->id], ['modalidad_id' => $modality->id]);
        $process = TramiteProceso::firstOrFail();
        foreach (['Documento de identidad', 'Certificado de calificaciones', 'Certificado de no impedimento', 'Plan de estudios', 'Sílabos certificados', 'Solicitud de homologación'] as $name) {
            DocumentoRequeridoProceso::firstOrCreate(['tramite_proceso_id' => $process->id, 'carrera_id' => $career->id, 'nombre_documento' => $name], ['descripcion' => 'Entregar el documento físico completo y legible al coordinador. Requisito de demostración.', 'activo' => true, 'obligatorio' => true]);
        }
        $users['estudiante']->antecedentesAcademicos()->firstOrCreate(['universidad_origen' => '[DEMO] Universidad de origen'], ['carrera_origen' => 'Sistemas', 'tipo_institucion' => 'publica', 'periodo_cursado' => '2024–2025']);
        if (! $users['estudiante']->solicitudesComoEstudiante()->where('carrera_id', $career->id)->exists()) {
            $solicitud = app(StudentSolicitudService::class)->create($users['estudiante'], ['coordinador_carrera_id' => $assignment->id, 'tramite_proceso_id' => $process->id, 'procedencia_estudios' => '[DEMO] Universidad de origen']);
            $documents = $solicitud->documentos()->orderBy('id')->get();
            foreach ($documents->take(2) as $document) {
                app(CoordinatorDocumentService::class)->review($users['coordinador'], $document->id, 'presentado', null);
                app(CoordinatorDocumentService::class)->review($users['coordinador'], $document->id, 'aprobado', null);
            }
            app(CoordinatorDocumentService::class)->review($users['coordinador'], $documents[2]->id, 'observado', 'Falta la firma y el sello de la institución de origen. Presenta una copia certificada.');
        }
        $this->command?->info('Usuarios locales: administrador.demo@example.test, coordinador.demo@example.test, estudiante.demo@example.test. Contraseña inicial: DemoUeb2026! (no se reemplazan contraseñas existentes).');
    }
}
