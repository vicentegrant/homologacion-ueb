# Contrato de la API REST

Para comenzar la integración, consultar la [guía para Frontend](guia-frontend.md), con ejemplos de conexión, orden de integración y comportamiento del flujo corregido.

Base: `/api/v1`. Los clientes deben enviar `Accept: application/json`. Las rutas protegidas requieren `Authorization: Bearer {token}`. Los cuerpos normales usan `Content-Type: application/json`; la carga de resoluciones usa `multipart/form-data`.

## Respuestas y errores

Las respuestas exitosas incluyen `success: true`. Los listados paginados usan la estructura de Laravel: `data`, `links` y `meta` (`current_page`, `last_page`, `per_page`, `total`). `per_page` admite de 1 a 100 y vale 15 por defecto, salvo el reporte, cuyo valor por defecto es 50.

Los errores de validación y de flujo (4xx) tienen `success: false` y `message`. Los errores 422 de validación de campos añaden `errors`; una precondición de negocio puede devolver solo `message`. Un error interno 500 debe tratarse de forma general sin depender de campos adicionales.

| Código | Significado |
| --- | --- |
| 200 | Consulta o actualización correcta. |
| 201 | Recurso creado. |
| 401 | Token ausente o inválido, o credenciales incorrectas. |
| 403 | Rol insuficiente o cuenta inactiva. |
| 404 | Recurso o archivo no encontrado. |
| 409 | Conflicto de estado, como auto-desactivación o resolución ya existente. |
| 422 | Validación fallida. |
| 500 | Error interno; no se exponen trazas ni SQL en producción. |

## Autenticación

| Método | Endpoint | Acceso | Entrada |
| --- | --- | --- | --- |
| POST | `/register` | Deshabilitado | Devuelve 403; no crea cuentas ni tokens. |
| POST | `/login` | Público, limitado | `email`, `password` |
| GET | `/me` | Cualquier cuenta activa | — |
| POST | `/logout` | Cualquier cuenta activa | — |
| GET | `/roles` | Administrador | — |

Las cuentas las crea el Administrador mediante `/admin/users`. Los valores API son `administrador`, `coordinador` y `estudiante`. Login está limitado a 10 solicitudes por minuto. Login devuelve `user`, `token` y `token_type: Bearer`. Logout revoca solo el token actual. Una cuenta inactiva no puede iniciar sesión ni reutilizar un token previo.

`GET /roles` devuelve `{ "success": true, "roles": ["administrador", "coordinador", "estudiante"], "data": [{ "id": 1, "nombre": "administrador" }] }`, con un elemento en `data` por cada rol existente (el ejemplo abrevia la lista). Los IDs dependen de la base; utilizar el catálogo y no constantes para `rol_id`.

```json
{
  "email": "admin@example.com",
  "password": "contraseña-segura"
}
```

## Usuarios administrativos

Todas las rutas siguientes requieren token activo y rol `Administrador`.

| Método | Endpoint | Descripción |
| --- | --- | --- |
| GET | `/admin/users` | Lista usuarios con paginación y filtros. |
| POST | `/admin/users` | Crea usuario, asigna un rol y registra al creador en una transacción. |
| GET | `/admin/users/{id}` | Devuelve perfil, rol, creador y carreras coordinadas. |
| PUT/PATCH | `/admin/users/{id}` | Actualiza exclusivamente campos permitidos. |
| PATCH | `/admin/users/{id}/status` | Activa o desactiva la cuenta. |

Filtros de listado: `search` (nombre, cédula o correo), `rol`, `cuenta_activa`, `order_by` (`id`, `nombres_completos`, `cedula`, `email`, `created_at`), `direction` (`asc`, `desc`), `page` y `per_page`. Un `rol` inexistente en `roles.nombre` devuelve 422 con un error en `errors.rol`.

Creación:

```json
{
  "nombres_completos": "Juan Pérez",
  "cedula": "1234567890",
  "email": "juan@example.com",
  "numero_celular": "0999999999",
  "password": "UnaClaveSegura123!",
  "rol_id": 2
}
```

`cedula` admite de 10 a 20 caracteres; `numero_celular`, de 7 a 20. Cédula y correo son únicos. `rol_id` debe existir. La contraseña se almacena con hash. Una actualización omite la contraseña si no se envía. El administrador autenticado no puede desactivar su propia cuenta ni quitarse su propio rol administrativo; esos conflictos devuelven 409. Al desactivar a otro usuario se revocan todos sus tokens.

Estado:

```json
{ "cuenta_activa": false }
```

## Coordinadores y carreras

| Método | Endpoint | Entrada/resultado |
| --- | --- | --- |
| GET | `/admin/careers` | Catálogo completo de carreras ordenado por nombre. |
| GET | `/admin/coordinators/{id}/careers` | Carreras asignadas al coordinador. |
| PUT | `/admin/coordinators/{id}/careers` | Reemplaza todas las asignaciones actuales. |

```json
{ "carrera_ids": [1, 3, 5] }
```

Los IDs deben existir y no repetirse. El usuario indicado debe tener el rol `Coordinador`; de lo contrario se devuelve 422. El índice único existente en `coordinador_carreras` también impide duplicados.

Si se intenta retirar una carrera que tiene estudiantes asignados, la operación devuelve 409 sin modificar ninguna asignación. Primero deben reasignarse esos estudiantes. La clave foránea impide también que un borrado directo elimine sus vínculos académicos.

## Estudiante: perfil y antecedentes

Estas rutas requieren token, cuenta activa y rol `Estudiante`. Siempre operan sobre el usuario autenticado; no aceptan un propietario elegido por el cliente.

| Método | Endpoint | Descripción |
| --- | --- | --- |
| GET | `/student/profile` | Perfil propio, antecedentes y carreras asignadas. |
| PATCH | `/student/profile` | Actualización parcial exclusivamente de `numero_celular`. |
| GET | `/student/antecedentes` | Antecedentes propios paginados (`page`, `per_page`, máximo 100). |
| POST | `/student/antecedentes` | Registra un antecedente propio. |
| GET | `/student/antecedentes/{id}` | Consulta un antecedente propio. |
| PATCH | `/student/antecedentes/{id}` | Actualiza parcialmente un antecedente propio. |

El perfil admite únicamente `numero_celular` (7–20 caracteres). Nombre, cédula, correo, roles, estado de cuenta, creador y contraseña se ignoran y no se modifican desde este endpoint.

Crear un antecedente requiere los cuatro campos siguientes; `PATCH` admite cualquier subconjunto, sin valores vacíos:

```json
{
  "universidad_origen": "Universidad de origen",
  "carrera_origen": "Sistemas",
  "tipo_institucion": "publica",
  "periodo_cursado": "2024-2025"
}
```

Universidad y carrera admiten hasta 255 caracteres; tipo de institución y período, hasta 100. Se conserva el esquema actual de texto libre. Un antecedente ajeno o inexistente devuelve el mismo 404. No se incluye borrado de antecedentes en esta primera entrega.

## Consulta de estudiantes

| Método | Endpoint | Descripción |
| --- | --- | --- |
| GET | `/admin/students` | Lista estudiantes; admite `search`, `cuenta_activa`, `page`, `per_page`. |
| GET | `/admin/students/{id}` | Perfil, antecedentes, carrera/coordinador y solicitudes existentes. |

Es un módulo administrativo de solo lectura. No crea antecedentes ni modifica análisis académicos.

## Solicitudes

| Método | Endpoint | Descripción |
| --- | --- | --- |
| GET | `/admin/solicitudes` | Listado paginado con filtros. |
| GET | `/admin/solicitudes/{id}` | Detalle administrativo estructurado. |

Filtros: `estado` (ID o nombre), `carrera` (ID), `tipo_tramite` (ID o nombre), `tipo_proceso` (ID o nombre), `estudiante`, `coordinador`, `fecha_desde`, `fecha_hasta`, `page`, `per_page`. `fecha_hasta` no puede ser anterior a `fecha_desde`. El filtro de estado usa el último registro de `historial_estados_solicitud`.

El detalle contiene, si existen: estudiante, coordinador, trámite/proceso, procedencia, estado actual, documentos y observaciones, historial, oficios, resultado y resolución. No expone rutas privadas de archivos.

## Resoluciones

| Método | Endpoint | Descripción |
| --- | --- | --- |
| POST | `/admin/solicitudes/{id}/resolucion` | Registra metadatos y almacena un PDF privado. |
| GET | `/admin/solicitudes/{id}/resolucion/download` | Descarga autorizada del PDF. |

La carga usa `multipart/form-data` con `numero_resolucion` único (máximo 100 caracteres), `fecha_aprobacion` y `archivo` PDF. El límite se configura con `MAX_PRIVATE_PDF_SIZE_KB` (10 MB por defecto). Solo se admite una resolución por solicitud, en estado `en_consejo`. Tanto Administrador como Coordinador finalizan la solicitud en `listo` al registrarla. Un estado incompatible devuelve 409 sin conservar el archivo cargado. La ruta interna nunca se devuelve; la respuesta incluye `download_url`.

## Reportes y estadísticas

| Método | Endpoint | Descripción |
| --- | --- | --- |
| GET | `/admin/reports/solicitudes` | Aplica los mismos filtros de solicitudes y devuelve filtros aplicados, total, agregados por estado, registros y paginación. |
| GET | `/admin/dashboard` | Totales de usuarios, usuarios por rol, activos/inactivos, solicitudes y solicitudes por estado. |

Los reportes administrativos y el dashboard entregan JSON; no incluyen interfaz ni exportación Excel. El informe técnico del Coordinador sí se genera como PDF paginado, sin dependencias adicionales.

## Estudiante: solicitudes, archivos y seguimiento

Todas las rutas requieren Sanctum, cuenta activa y rol `Estudiante`. El propietario se obtiene del token. Los recursos ajenos y los inexistentes devuelven el mismo 404, también cuando un documento pertenece a otra solicitud.

| Método | Endpoint | Descripción |
| --- | --- | --- |
| GET | `/student/catalogo` | Combinaciones de trámite/proceso y carreras/coordinadores asignados al estudiante. |
| GET | `/student/solicitudes` | Solicitudes propias paginadas; filtros `estado` (nombre), `page` y `per_page` (1–100). |
| POST | `/student/solicitudes` | Crea una solicitud pendiente con sus documentos requeridos. |
| GET | `/student/solicitudes/{id}` | Detalle, requisitos, archivos, observaciones, historial y resultado propios. |
| PATCH | `/student/solicitudes/{id}` | Modifica `procedencia_estudios` mientras esté pendiente. |
| POST | `/student/solicitudes/{id}/enviar` | Envía la solicitud completa o corregida a revisión. |
| POST | `/student/solicitudes/{id}/documentos/{documento}` | Carga o reemplaza el PDF de un documento requerido. |
| GET | `/student/solicitudes/{id}/documentos/{documento}/download` | Descarga privada del documento propio. |
| GET | `/student/solicitudes/{id}/resolucion/download` | Descarga privada de la resolución cuando el estado es `listo`. |
| GET | `/student/notificaciones` | Avisos propios paginados; `sin_leer=1` filtra los pendientes. |
| PATCH | `/student/notificaciones/{uuid}/leer` | Marca un aviso propio como leído; repetirlo conserva la fecha de lectura. |

### Creación y requisitos

Primero se consulta el catálogo y se selecciona una asignación del estudiante. Si no tiene asignaciones, debe gestionarlas con la administración; el estudiante no puede asignarse una carrera o coordinador arbitrarios.

```json
{
  "coordinador_carrera_id": 1,
  "tramite_proceso_id": 1,
  "procedencia_estudios": "Universidad de origen"
}
```

`procedencia_estudios` admite hasta 255 caracteres. Se requiere un coordinador activo y al menos un requisito configurado. Los requisitos generales del trámite y los específicos de la carrera se incorporan a la solicitud como documentos pendientes. Incorporar requisitos nuevos al catálogo no modifica solicitudes ya creadas. La carrera queda guardada en `solicitudes.carrera_id`; no cambia cuando se modifica una asignación posterior.

No puede existir otra solicitud activa del mismo estudiante, carrera y trámite. Para esta regla, `listo` y `rechazado` son estados finales. La creación devuelve 201; los conflictos de configuración, coordinador o duplicidad devuelven 409 sin dejar registros parciales. La creación está limitada a 20 solicitudes/minuto y los uploads a 30/minuto.

### Envío y correcciones

Flujo acordado:

```text
pendiente → completar documentos → enviar → en_revision
observado → corregir documentos señalados → reenviar → en_revision
```

El envío exige antecedentes académicos y todos los archivos requeridos presentes en almacenamiento, con estado `presentado` o `aprobado` (estos últimos deben tener `validez=true`). Los faltantes devuelven 422 en `errors.antecedentes` o `errors.documentos`. Enviar de nuevo una solicitud ya en revisión devuelve 409 y no duplica el historial. El estudiante no puede aprobar documentos ni establecer estados administrativos.

La carga usa `multipart/form-data`, campo `archivo`, PDF validado por contenido MIME. El límite proviene de `MAX_PRIVATE_PDF_SIZE_KB` (10240 KB por defecto). `upload_max_filesize` y `post_max_size` deben admitir un valor igual o mayor. Cambiar la extensión de un archivo no lo convierte en PDF válido.

En una solicitud pendiente se pueden cargar/reemplazar documentos `pendiente` o `presentado`. En una solicitud observada se permiten documentos `observado` o todavía `pendiente`; los aprobados quedan protegidos. Una corrección vuelve a `presentado`, reinicia `validez=false` y conserva las observaciones históricas. El archivo anterior se elimina solo después de persistir el reemplazo; si falla la base, se conserva el anterior y se elimina el nuevo. No se ofrece un archivo histórico de versiones de PDF.

Los archivos se guardan en el disco privado `local`. La API entrega enlaces de descarga autenticada, nunca rutas físicas. Un archivo ausente devuelve 404. La resolución no se expone al estudiante antes del estado `listo`, aunque ya haya sido registrada por el Administrador.

### Notificaciones

Los avisos se guardan en la base de datos y se consultan desde el frontend. El correo se habilita con `STUDENT_MAIL_NOTIFICATIONS=true` y se procesa mediante cola, por lo que SMTP no bloquea la transacción principal. Se generan al crear registros Eloquent de historial de estado, observación documental o resolución; las escrituras SQL directas o eventos deshabilitados no generan avisos.

Los avisos contienen `solicitud_id`, `evento`, `mensaje` y `url`. Los tipos son `estado_actualizado`, `documento_observado` y `resolucion_registrada`. El listado devuelve `data`, `links`, `meta` y el total `sin_leer`. Los avisos de una transacción revertida se revierten junto con sus datos.

## Alcance restante

El flujo backend de Estudiante y Coordinador está implementado. El frontend es independiente. Los catálogos y asignaciones institucionales reales deben configurarse antes de operar con estudiantes reales; los datos `[DEMO]` son exclusivamente de desarrollo.

## Coordinador

Todas las rutas de esta sección requieren `Authorization: Bearer {token}`, cuenta activa y rol `Coordinador`. El servidor obtiene al Coordinador desde el token; ningún body acepta `coordinador_id`. Los recursos se limitan a sus filas en `coordinador_carreras`. Un ID ajeno devuelve 404 para no revelar su existencia; un filtro explícito por una carrera no asignada devuelve 403.

### Catálogo, estudiantes, solicitudes y reportes

| Método | Endpoint | Query/body y validación | Respuesta correcta | Errores específicos |
| --- | --- | --- | --- | --- |
| GET | `/coordinator/catalogo` | — | 200; carreras permitidas, estados, trámites, ciclos y transiciones | 401, 403 |
| GET | `/coordinator/students` | `search`, `cuenta_activa`, `carrera`, `per_page` 1–100 | 200; `data`, `links`, `meta` | 403 carrera ajena; 422 filtro inválido |
| GET | `/coordinator/students/{id}` | `id` entero | 200; perfil, antecedentes, carreras y solicitudes permitidas | 404 ajeno/inexistente |
| GET | `/coordinator/solicitudes` | `search`, `estado`, `carrera`, `tipo_tramite`, `tipo_proceso`, `estudiante`, `fecha_desde`, `fecha_hasta`, `per_page` | 200 paginado | 403 carrera ajena; 422 filtros inválidos |
| GET | `/coordinator/solicitudes/{id}` | `id` entero | 200; expediente, documentos, historial, comparaciones, resultado y resolución | 404 ajena/inexistente |
| GET | `/coordinator/reports/solicitudes` | Mismos filtros del listado; `per_page` predeterminado 50 | 200; filtros, total, agrupación por estado, registros y paginación | 403, 422 |

Las búsquedas de estudiantes consideran nombres, cédula y correo. El detalle no expone contraseñas ni rutas internas de almacenamiento.

### Documentos y verificaciones

| Método | Endpoint | Query/body y validación | Respuesta correcta | Errores específicos |
| --- | --- | --- | --- | --- |
| GET | `/coordinator/solicitudes/{id}/documents` | — | 200; documentos y revisiones | 404 solicitud ajena |
| GET | `/coordinator/documents/{id}` | — | 200; requisito, estado, observaciones y verificaciones | 404 documento ajeno |
| GET | `/coordinator/documents/{id}/download` | — | 200 `application/pdf`, descarga privada | 404 ajeno/archivo ausente |
| PATCH | `/coordinator/documents/{id}/review` | `estado`: `aprobado`/`observado`; `observacion` obligatoria al observar, máx. 2000 | 200; documento actualizado | 404 ajeno; 409 etapa/archivo; 422 body |
| POST | `/coordinator/documents/{id}/verification` | `estado` booleano | 200; verificación creada o actualizada | 404 ajeno; 409 etapa; 422 body |

```json
{
  "estado": "observado",
  "observacion": "El documento no contiene todas las páginas."
}
```

Observar crea un registro histórico en `observaciones_documentacion`, cambia la solicitud a `observado` y notifica al Estudiante. Se permite continuar revisando otros documentos en `observado` sin duplicar esa transición. Observar o reemplazar un archivo elimina sus verificaciones vigentes; el reemplazo conserva observaciones y vuelve el documento a `presentado` con `validez=false`. Verificar exige un archivo existente en estado `presentado` o `aprobado`. El estudiante reenvía la solicitud a `en_revision`; solo desde esa etapa, con todos los archivos presentes, aprobados y con al menos una verificación por documento y todas positivas, se avanza a `en_proceso`.

### Mallas, asignaturas y sílabos

| Método | Endpoint | Query/body y validación | Respuesta correcta | Errores específicos |
| --- | --- | --- | --- | --- |
| GET | `/coordinator/curricula` | `search`, `tipo`, `carrera`, `estudiante`, `activa`, `per_page` | 200 paginado | 403 carrera ajena; 422 |
| POST | `/coordinator/curricula` | `nombre`; `tipo`; `carrera_id` requerido si institucional; `estudiante_id` requerido si origen; `activa` opcional | 201; malla | 403 contexto ajeno; 422 |
| GET | `/coordinator/curricula/{id}` | — | 200; malla, creador y asignaturas | 404 ajena |
| PUT/PATCH | `/coordinator/curricula/{id}` | `nombre` y/o `activa` | 200; malla actualizada | 404 ajena; 422 |
| PATCH | `/coordinator/curricula/{id}/status` | `activa` booleano | 200; malla actualizada | 404, 422 |
| GET | `/coordinator/curricula/{id}/subjects` | `page` | 200 paginado | 404 malla ajena |
| POST | `/coordinator/curricula/{id}/subjects` | código, nombre, créditos ≥0, ciclo válido, carga horaria ≥0 | 201; asignatura | 404; 409 código duplicado; 422 |
| GET | `/coordinator/subjects/{id}` | — | 200; asignatura, malla y temas | 404 ajena |
| PUT/PATCH | `/coordinator/subjects/{id}` | subconjunto de campos de asignatura | 200; asignatura actualizada | 404; 409 código duplicado; 422 |
| GET | `/coordinator/subjects/{id}/syllabus-topics` | — | 200; temas ordenados | 404 asignatura ajena |
| POST | `/coordinator/subjects/{id}/syllabus-topics` | `tema`, `unidad_analitica`, máx. 255 | 201; tema | 404, 422 |
| PUT | `/coordinator/syllabus-topics/{id}` | `tema` y/o `unidad_analitica` | 200; tema actualizado | 404 ajeno; 422 |

Los ciclos admitidos son `primero` a `decimo`. `hr_carga_horaria` existe en la migración real y es obligatorio. Origen/destino se deduce de `mallas_curriculares.tipo`; no existen campos redundantes.

### Comparaciones, resultado e informe técnico

| Método | Endpoint | Query/body y validación | Respuesta correcta | Errores específicos |
| --- | --- | --- | --- | --- |
| GET | `/coordinator/solicitudes/{id}/comparisons` | — | 200; matriz con códigos, créditos, ciclo y porcentaje | 404 solicitud ajena |
| POST | `/coordinator/solicitudes/{id}/comparisons` | IDs origen/destino distintos; porcentaje 0–100; observación opcional | 201; comparación | 403 destino ajeno; 409 etapa/duplicado; 422 |
| PUT | `/coordinator/comparisons/{id}` | cuerpo completo de comparación | 200; comparación actualizada | 403, 404, 409, 422 |
| DELETE | `/coordinator/comparisons/{id}` | — | 200; confirmación | 404 ajena; 409 fuera de análisis |
| GET | `/coordinator/solicitudes/{id}/result` | — | 200; resultado | 404 solicitud/resultado |
| POST | `/coordinator/solicitudes/{id}/result` | `conclusion_general`: `total`, `parcial`, `rechazada`; créditos ≥0 | 201; resultado | 404 ajena; 409 requisitos, etapa, comparación o duplicado; 422 |
| POST | `/coordinator/solicitudes/{id}/technical-report` | — | 201; metadatos del informe | 404 ajena; 409 sin resultado aprobatorio |
| GET | `/coordinator/solicitudes/{id}/technical-report` | — | 200 `application/pdf` | 404 ajena/informe ausente |

La asignatura origen debe pertenecer a una malla `origen` del Estudiante de la solicitud. La de destino debe pertenecer a una malla `institucional` de la carrera exacta de la solicitud. El resultado es único por solicitud y usa siempre el Coordinador autenticado. `total`/`parcial` cambia a `aprobado`; `rechazada` cambia a `rechazado`.

El informe se genera en `aprobado`, distribuye el texto en tantas páginas como sean necesarias y conserva acentos y ñ. Si ya existe, repetir el POST devuelve sus metadatos (201) sin sobrescribirlo ni alterar la fecha original. Si el archivo se pierde después de pasar a Consejo, generar otro devuelve 409: debe restaurarse desde respaldo. La descarga devuelve 404 si el archivo no existe. Porcentaje y créditos son evaluaciones ingresadas por el Coordinador; no se aplica un umbral automático institucional.

### Estados y resolución

| Método | Endpoint | Query/body y validación | Respuesta correcta | Errores específicos |
| --- | --- | --- | --- | --- |
| POST | `/coordinator/solicitudes/{id}/state` | `estado` existente; `observacion` obligatoria para rechazo | 200; solicitud con estado actual | 404 ajena; 409 transición/condición; 422 |
| POST | `/coordinator/solicitudes/{id}/resolution` | `multipart/form-data`: número único máx. 100, fecha, PDF con límite configurable | 201; resolución y solicitud `listo` | 404 ajena; 409 etapa/duplicado; 422 |
| GET | `/coordinator/solicitudes/{id}/resolution/download` | — | 200 `application/pdf` | 404 ajena/archivo ausente |

Transiciones válidas y condiciones:

```text
pendiente → en_revision       documentos completos
en_revision → observado      existe documento observado
observado → en_revision      documentos observados corregidos
en_revision → en_proceso     documentos y verificaciones aprobados
en_revision → rechazado      motivo documental obligatorio
observado → rechazado        motivo documental obligatorio
en_proceso → aprobado        resultado total o parcial
en_proceso → rechazado       resultado rechazado y motivo
aprobado → en_consejo        informe técnico generado
en_consejo → listo           resolución externa registrada
en_consejo → rechazado       decisión motivada del Consejo
```

Cada transición crea una fila, nunca sobrescribe historial, y registra `usuario_responsable_id`, `etapa_origen`, observación y timestamps. Las operaciones críticas usan transacción y bloqueo de la solicitud.

## Contrato para integración Frontend

- URL base local: `http://127.0.0.1:8000/api/v1`; en otros ambientes usar `APP_URL`.
- Headers: `Accept: application/json` y `Authorization: Bearer {token}`. JSON usa `Content-Type: application/json`; resolución usa `multipart/form-data`.
- `GET /me` incluye `roles` y, para Coordinador, `carreras_coordinadas`.
- Listados grandes usan `page`, `per_page`, `data`, `links` y `meta`; el reporte incluye su bloque `paginacion`.
- Las descargas usan únicamente `download_url` o endpoints documentados; nunca se construyen rutas de `storage`.
- Errores: 401 token, 403 rol/carrera, 404 aislamiento de recurso, 409 conflicto de flujo y 422 validación con `errors`.
- El frontend debe refrescar el detalle tras cada escritura porque una revisión, resultado o resolución puede cambiar automáticamente el estado.
