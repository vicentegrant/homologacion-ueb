<?php

namespace App\Observers;

use App\Models\HistorialEstadoSolicitud;
use App\Models\ObservacionDocumentacion;
use App\Models\ResolucionSolicitud;
use App\Notifications\SolicitudActivityNotification;

class StudentActivityObserver
{
    public function created(HistorialEstadoSolicitud|ObservacionDocumentacion|ResolucionSolicitud $record): void
    {
        if ($record instanceof ObservacionDocumentacion) {
            $solicitud = $record->solicitudDocumento->solicitud;
            $event = 'documento_observado';
            $message = $record->observacion;
        } elseif ($record instanceof HistorialEstadoSolicitud) {
            $solicitud = $record->solicitud;
            $event = 'estado_actualizado';
            $message = 'Su solicitud cambió al estado: '.$record->estadoSolicitud->nombre.'.';
        } else {
            $solicitud = $record->solicitud;
            $event = 'resolucion_registrada';
            $message = 'Se registró una resolución. Podrá descargarla cuando la solicitud esté lista.';
        }

        $solicitud->estudiante->notify(new SolicitudActivityNotification($solicitud->id, $event, $message));
    }
}
