<?php

namespace App\Services;

use App\Models\ComparacionAsignatura;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TechnicalReportService
{
    public function __construct(private CoordinatorAccessService $access, private SolicitudWorkflowService $workflow) {}

    public function generate(User $coordinator, Solicitud $solicitud): string
    {
        $newPath = null;
        try {
            return DB::transaction(function () use ($coordinator, $solicitud, &$newPath): string {
                $locked = $this->access->solicitud($coordinator, $solicitud->id, true);
                $locked->load([
                    'estudiante', 'carrera', 'tramiteProceso.tipoTramite', 'tramiteProceso.tipoProceso',
                    'comparacionesAsignaturas.asignaturaOrigen', 'comparacionesAsignaturas.asignaturaDestino', 'resultado.coordinador',
                ]);
                abort_unless($locked->resultado !== null, 409, 'La solicitud todavía no tiene resultado académico.');
                abort_unless(in_array($locked->resultado->conclusion_general, ['total', 'parcial'], true), 409, 'El resultado no permite generar un informe técnico.');
                $existingPath = $locked->resultado->ruta_informe_tecnico;
                if ($existingPath && Storage::disk('local')->exists($existingPath)) {
                    return $existingPath;
                }
                abort_unless($this->workflow->currentState($locked) === 'aprobado', 409, 'El informe solo puede generarse en la etapa aprobada antes de remitirlo al Consejo.');

                $path = $newPath = 'informes-tecnicos/solicitud-'.$locked->id.'-'.bin2hex(random_bytes(8)).'.pdf';
                $stored = Storage::disk('local')->put($path, $this->pdf($this->lines($locked)));
                if (! $stored) {
                    throw new RuntimeException('No fue posible almacenar el informe técnico.');
                }
                $locked->resultado->update([
                    'ruta_informe_tecnico' => $path,
                    'informe_generado_at' => now(),
                ]);

                return $path;
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
    }

    /** @return list<string> */
    private function lines(Solicitud $solicitud): array
    {
        $lines = [
            'INFORME TÉCNICO DE RECONOCIMIENTO Y HOMOLOGACIÓN',
            'Solicitud: '.$solicitud->id,
            'Estudiante: '.$solicitud->estudiante->nombres_completos,
            'Cédula: '.$solicitud->estudiante->cedula,
            'Carrera: '.$solicitud->carrera->nombre,
            'Tramite: '.$solicitud->tramiteProceso->tipoTramite->nombre,
            'Proceso: '.$solicitud->tramiteProceso->tipoProceso->nombre,
            'Procedencia: '.$solicitud->procedencia_estudios,
            '',
            'COMPARACIONES ACADEMICAS',
        ];

        foreach ($solicitud->comparacionesAsignaturas as $comparison) {
            /** @var ComparacionAsignatura $comparison */
            $lines[] = sprintf(
                '%s - %s => %s - %s: %s%%',
                $comparison->asignaturaOrigen->codigo_asignatura,
                $comparison->asignaturaOrigen->nombre_asignatura,
                $comparison->asignaturaDestino->codigo_asignatura,
                $comparison->asignaturaDestino->nombre_asignatura,
                $comparison->porcentaje_coincidencia,
            );
            if ($comparison->observacion) {
                $lines[] = 'Observacion: '.$comparison->observacion;
            }
        }

        $lines[] = '';
        $lines[] = 'Resultado: '.$solicitud->resultado->conclusion_general;
        $lines[] = 'Creditos reconocidos: '.$solicitud->resultado->total_creditos_reconocidos;
        $lines[] = 'Coordinador responsable: '.$solicitud->resultado->coordinador->nombres_completos;
        $lines[] = 'Fecha de generacion: '.now()->toDateTimeString();

        return $lines;
    }

    /** @param list<string> $lines */
    private function pdf(array $lines): string
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $encoded = mb_convert_encoding(str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $line), 'Windows-1252', 'UTF-8');
            foreach (explode("\n", $encoded) as $paragraph) {
                array_push($wrapped, ...explode("\n", wordwrap($paragraph, 85, "\n", true)));
            }
        }
        $pages = array_chunk($wrapped, 44);
        $pageCount = count($pages);
        $kids = [];
        foreach (array_keys($pages) as $index) {
            $kids[] = (4 + $index * 2).' 0 R';
        }
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
        ];
        foreach ($pages as $pageIndex => $page) {
            $content = "BT\n/F1 10 Tf\n50 790 Td\n";
            foreach ($page as $index => $line) {
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $content .= ($index === 0 ? '' : "0 -16 Td\n").'('.$escaped.") Tj\n";
            }
            $content .= "ET\nBT\n/F1 9 Tf\n50 40 Td\n(Pagina ".($pageIndex + 1).' de '.$pageCount.") Tj\nET\n";
            $streamId = 5 + $pageIndex * 2;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$streamId.' 0 R >>';
            $objects[] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
