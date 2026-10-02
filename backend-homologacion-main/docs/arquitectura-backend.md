# Arquitectura del backend

```text
React → HTTP/JSON → Laravel 13 → Eloquent → PostgreSQL
```

La API está versionada bajo `/api/v1`. `routes/api.php` aplica Sanctum, cuenta activa y rol; los Form Requests validan entrada; los controladores coordinan HTTP; los servicios contienen transacciones y reglas; los Resources controlan la salida.

## Límites de seguridad

- Roles oficiales en `roles.nombre` y asignaciones en `user_has_rol`.
- Middleware propio para `administrador`, `coordinador` y `estudiante`.
- `CoordinatorAccessService` limita todas las consultas a carreras asignadas.
- Los estudiantes solo operan sobre recursos propios.
- El Administrador crea las cuentas. El registro público responde 403; el Estudiante solo modifica su celular.
- Los PDF permanecen en el disco privado `local`; nunca se expone una ruta física.
- Las operaciones sensibles usan transacciones, bloqueos `FOR UPDATE` y restricciones únicas.

## Servicios principales

- `StudentSolicitudService`: creación, envío y corrección de solicitudes.
- `CoordinatorDocumentService`: revisión y verificación documental.
- `AcademicAnalysisService`: comparaciones y resultado único.
- `SolicitudWorkflowService`: transiciones, precondiciones e historial.
- `TechnicalReportService`: informe técnico PDF privado.
- `ResolutionService`: resolución externa y cierre.

La revisión y la carga de documentos bloquean primero la solicitud para serializar cambios de estado y reemplazos. Una observación o reemplazo invalida las verificaciones vigentes. El informe técnico se pagina y se conserva una vez generado. El registro de resolución aplica la misma transición `en_consejo → listo` para Administrador y Coordinador.

Los observers generan notificaciones internas después de persistir historial, observaciones o resoluciones. El correo opcional implementa `ShouldQueue`, de modo que una indisponibilidad SMTP no revierte la lógica principal.

## Máquina de estados

```text
pendiente → en_revision
en_revision → observado | en_proceso | rechazado
observado → en_revision | rechazado
en_proceso → aprobado | rechazado
aprobado → en_consejo
en_consejo → listo | rechazado
```

`historial_estados_solicitud` registra el estado nuevo, `etapa_origen`, usuario responsable, observación y fecha. Los estados terminales son `listo` y `rechazado`.
