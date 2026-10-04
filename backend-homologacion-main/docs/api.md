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
| GET | `/me` | Cuenta activa con cambio obligatorio completado | — |
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
| DELETE | `/admin/users/{id}` | Elimina una cuenta sin historial; 409 si es la propia cuenta o tiene registros relacionados. Revoca tokens, sesiones y enlaces de recuperación. |

Filtros de listado: `search` (nombre, cédula o correo), `rol`, `cuenta_activa`, `order_by` (`id`, `nombres_completos`, `cedula`, `email`, `created_at`), `direction` (`asc`, `desc`), `page` y `per_page`. Un `rol` inexistente en `roles.nombre` devuelve 422 con un error en `errors.rol`.

Creación:

```json
{
  "nombres_completos": "Juan Pérez",
  "tipo_identificacion": "cedula",
  "cedula": "1234567890",
  "email": "juan@example.com",
  "numero_celular": "0999999999",
  "rol_id": 2
}
```

`tipo_identificacion` selecciona cédula (10 dígitos) o pasaporte (5–20 letras mayúsculas/números); `numero_celular`, exactamente 10 dígitos. Cédula y correo son únicos. `rol_id` debe existir. El sistema usa la identificación validada como contraseña inicial, la guarda hasheada y exige cambiarla; no se permite establecerla desde el formulario administrativo. El administrador autenticado no puede desactivar su propia cuenta ni quitarse su propio rol administrativo; esos conflictos devuelven 409. Al desactivar a otro usuario se revocan todos sus tokens.

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

El perfil admite únicamente `numero_celular` (10 dígitos). Nombre, cédula, correo, roles, estado de cuenta, creador y contraseña se ignoran y no se modifican desde este endpoint.

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
| POST | `/student/solicitudes/{id}/enviar` | Retirado: 410. La revisión inicia con recepción presencial. |
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

No puede existir otra solicitud activa del mismo estudiante, carrera y trámite. Para esta regla, `listo` y `rechazado` son estados finales. La creación devuelve 201; los conflictos de configuración, coordinador o duplicidad devuelven 409 sin dejar registros parciales. La creación está limitada a 20 solicitudes/minuto ; las cargas del estudiante están retiradas.

## Gestión presencial y nuevos CRUD

La documentación se entrega físicamente. **Solo el coordinador valida el checklist**; el administrador configura los requisitos y el estudiante consulta el resultado. Los PDF históricos siguen disponibles mediante descarga privada, pero ya no se cargan ni reemplazan desde el estudiante. La carga de resoluciones y la generación del informe técnico PDF se conservan.

### Identificación y estudiantes

Seleccionar `tipo_identificacion` (`cedula` o `pasaporte`) antes de ingresar `cedula` (se conserva este nombre de campo por compatibilidad). Cédula: exactamente 10 dígitos. Pasaporte: 5–20 letras mayúsculas o números, sin espacios. Es validación de formato, no una consulta al Registro Civil. Ambos identificadores son únicos. El filtro `tipo_identificacion` se admite en usuarios administrativos y estudiantes del coordinador; `search` busca nombre, identificación o correo.

Rutas relativas a `/api/v1`:

| Método y ruta | Comportamiento |
| --- | --- |
| `GET /coordinator/students` | Listar y filtrar estudiantes dentro del alcance del coordinador. |
| `POST /coordinator/students` | Crear exclusivamente una cuenta de estudiante con identificación como contraseña inicial, exigir cambio y asignar destino. |
| `GET /coordinator/students/{id}` | Consultar estudiante y asignaciones visibles. |
| `PUT /coordinator/students/{id}` | Editar datos y destino de un estudiante actualmente asignado. |
| `DELETE /coordinator/students/{id}` | Desactivar conservando el historial; 409 si tiene solicitudes activas. |
| `PATCH /coordinator/students/{id}/status` | Activar/desactivar mediante `cuenta_activa`. |

Crear/editar envía `nombres_completos`, `tipo_identificacion`, `cedula`, `email`, `numero_celular`, `carrera_id`, `modalidad_id`, `procedencia` y `periodo_cursado`. La interfaz exige elegir `interna` o `externa`:

- Interna: enviar `carrera_origen_id` del catálogo UEB, activa y distinta del destino; puede pertenecer a otra coordinación. El servidor fija `universidad_origen` como Universidad Estatal de Bolívar y `tipo_institucion` como `publica`.
- Externa: enviar `universidad_origen`, `tipo_institucion` (`publica`, `privada` o `instituto`) y `carrera_origen` como texto. No requiere una carrera del catálogo UEB ni crea una en él. Puede tener el mismo nombre que el destino por tratarse de otra institución.

Destino: una carrera activa de facultad activa, asignada al coordinador, con una modalidad activa habilitada. El catálogo devuelve `universidad_interna` y `carreras_origen` activas con su facultad. Ciclo: desde `Primer ciclo / semestre` hasta `Décimo ciclo / semestre`; los períodos históricos siguen siendo legibles. Celular: 10 dígitos. No enviar contraseña ni rol.

La migración `2026_10_04_183009_add_origin_provenance_to_antecedentes_academicos_table` agrega procedencia y referencia interna al antecedente. Los registros antiguos quedan sin clasificar; la interfaz exige revisión explícita al editarlos. Por compatibilidad, clientes antiguos sin `procedencia` conservan la validación anterior. No se pueden cambiar institución, procedencia, carreras o modalidad durante una solicitud activa. Los datos de origen clasificados se modifican desde coordinación; el estudiante conserva la edición del ciclo. Todo el registro se guarda en una transacción.

### Catálogos del administrador

`GET /admin/catalogs` devuelve `facultades`, `carreras`, `modalidades`, `requisitos` y `tramites`. Para cada catálogo (`facultades`, `carreras`, `modalidades`, `requisitos`):

- `POST /admin/catalogs/{catalog}`: crear.
- `GET /admin/catalogs/{catalog}/{id}`: consultar.
- `PUT /admin/catalogs/{catalog}/{id}`: editar.
- `DELETE /admin/catalogs/{catalog}/{id}`: eliminar solo si no tiene referencias; de lo contrario 409. Puede desactivarse con `activa=false` mediante PUT.

Todos requieren `nombre` y `activa`. Carreras: también `facultad_id` y `modalidad_ids` (lista no vacía). Requisitos: `tramite_proceso_id`, `carrera_id` nullable (null = todas las carreras), `descripcion` nullable y `obligatorio` booleano. Un destino con alumnos asignados no puede perder sus modalidades en uso. La configuración se incorpora como copia en cada solicitud nueva: editar/desactivar un requisito no altera expedientes anteriores. Debe existir al menos un requisito obligatorio activo al crear una solicitud.

Modalidades iniciales: Presencial, Híbrida y En línea, publicadas en el [portal de pregrado de la UEB](https://www.ueb.edu.ec/index.php/pregrado?mode=hibrida). El administrador selecciona las ofrecidas por cada carrera. Los datos marcados `[DEMO]` son ejemplos y no representan el catálogo institucional oficial.

### Checklist y transiciones automáticas

`PATCH /coordinator/documents/{id}/review` acepta:

| `estado` | Uso |
| --- | --- |
| `presentado` | Registrar recepción física o recepción de una corrección. No implica aprobación. |
| `aprobado` | Validar un documento recibido. Registra también la verificación en la misma transacción. |
| `observado` | Indicar incumplimiento; `observacion` es obligatoria (hasta 2000 caracteres). Invalida verificaciones previas. |

El primer registro inicia `en_revision`. Un requisito obligatorio observado pasa la solicitud a `observado`. Recibir las correcciones pendientes permite volver a `en_revision`. Cuando todos los requisitos obligatorios están validados se pasa automáticamente a `en_proceso`, para análisis académico. No se aprueba automáticamente la homologación. En etapas posteriores ya no se modifica el checklist.

El estudiante obtiene `progreso_documental` (0–100) en el detalle y consulta `documentos`, con `obligatorio`, `presentado`, `recibido_at`, `revisado_at`, responsables y observaciones. El porcentaje es `floor(100 × obligatorios validados / total obligatorios)`; sin requisitos es 0. Los complementarios no incrementan ni bloquean el porcentaje. Se conservan el historial de estados, las observaciones y una auditoría en `historial_documentos`.

La antigua ruta de subida del estudiante fue eliminada y devuelve 404. Las rutas `POST /student/solicitudes/{id}/enviar` y `POST /coordinator/documents/{id}/verification` siguen deshabilitadas (410 para recursos propios). No construir controles de subida, reenvío o doble verificación. `puede_enviar` siempre es false.

### Notificaciones

Los avisos se guardan en la base de datos y se consultan desde el frontend. El correo se habilita con `STUDENT_MAIL_NOTIFICATIONS=true` y se procesa mediante cola, por lo que SMTP no bloquea la transacción principal. Se generan al crear registros Eloquent de historial de estado, observación documental o resolución; las escrituras SQL directas o eventos deshabilitados no generan avisos.

Los avisos contienen `solicitud_id`, `evento`, `mensaje` y `url`. Los tipos son `estado_actualizado`, `documento_observado` y `resolucion_registrada`. El listado devuelve `data`, `links`, `meta` y el total `sin_leer`. Los avisos de una transacción revertida se revierten junto con sus datos.

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

Consultar el apartado «Checklist y transiciones automáticas». GET de documentos y descargas históricas conserva el alcance por carrera. PATCH review recibe `presentado`, `aprobado` u `observado`; la verificación separada está retirada (410).

### Mallas, asignaturas y sílabos

| Método | Endpoint | Query/body y validación | Respuesta correcta | Errores específicos |
| --- | --- | --- | --- | --- |
| GET | `/coordinator/curricula` | `search`, `tipo`, `carrera`, `estudiante`, `activa`, `include_subjects`, `per_page` | 200 paginado | 403 carrera ajena; 422 |
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

`include_subjects=1` incluye las asignaturas de cada malla en la misma respuesta, sin temas de sílabo. El análisis académico utiliza esta opción para evitar una petición de detalle por cada malla. Mantiene los mismos filtros, paginación y permisos; sin esta opción el listado conserva su respuesta ligera.

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


## Contraseña temporal y recuperación

Las cuentas creadas por `POST /api/v1/admin/users` usan su correo como usuario y su identificación validada como contraseña inicial, guardada únicamente como hash. No enviar `password` al crear o editar usuarios: se rechaza con 422. Las cuentas existentes conservan su acceso. La contraseña inicial no tiene caducidad por tiempo; el cambio es obligatorio antes de acceder al panel. El reenvío conserva su contraseña aleatoria con caducidad de 24 horas (`TEMPORARY_PASSWORD_HOURS`). Se almacena únicamente su hash; si el envío falla, la cuenta se conserva y la respuesta incluye `credentials_email_sent=false`. Solo su creador puede reenviar credenciales mediante POST a `/admin/users/{id}/credentials` o `/coordinator/students/{id}/credentials`; se genera una nueva contraseña y se revocan accesos anteriores.

`POST /login` devuelve `user.must_change_password` y el alias compatible `user.password_temporal`. Si es `true`, mostrar exclusivamente cambio de contraseña o cierre de sesión. Todas las demás rutas autenticadas, incluido `GET /me`, devuelven 403 con `code: PASSWORD_CHANGE_REQUIRED`. Tras el cambio, `/me` vuelve a estar disponible e incluye `password_changed_at`.

Rutas relativas a `/api/v1`:

| Método y ruta | Datos | Acceso |
| --- | --- | --- |
| `POST /change-password` | `current_password`, `password`, `password_confirmation` | Bearer, cuenta activa; permitido durante el primer ingreso |
| `POST /forgot-password` | `email` | Público, limitado; respuesta genérica para no revelar cuentas |
| `POST /reset-password` | `email`, `token`, `password`, `password_confirmation` | Público, limitado; token válido durante 60 minutos y de un solo uso |

La nueva contraseña requiere al menos 8 caracteres, mayúscula, minúscula, número y símbolo; debe diferir de la identificación y de la contraseña actual. Al cambiar o recuperar la contraseña se revocan todos los accesos anteriores y se guarda `password_changed_at`. `/change-password` devuelve un nuevo `token` Bearer: sustituir el token local, consultar `/me` y abrir el panel del rol. `/reset-password` exige volver al login. Recuperar la contraseña también permite activar una cuenta cuya contraseña temporal caducó. Las cuentas inactivas no reciben enlaces.

El enlace de recuperación abre el frontend en `/restablecer-contrasena?token=...&email=...`. El frontend incluido ya implementa estas pantallas. `FRONTEND_URL` debe apuntar al frontend; si contiene varios orígenes, el primero se utiliza para los correos.

En desarrollo usar `MAIL_MAILER=smtp` y las credenciales del inbox de Mailtrap. El envío de credenciales se bloquea para transportes de log y failover para evitar registrar contraseñas temporales. Para entrega real se requiere configurar el transporte SMTP en `.env`, limpiar la configuración y probar la recepción con una cuenta controlada. No publicar `.env` ni registros que contengan contraseñas temporales o enlaces.

Al actualizar ejecutar `php artisan migrate --no-interaction`. No usar `migrate:fresh` sobre datos que se quieran conservar. La nueva migración añade `password_temporal`, copiando el estado existente de `must_change_password` sin reiniciar contraseñas. Los campos se sincronizan desde el modelo.
## Requisitos configurados por el coordinador

En el menú del coordinador, «Requisitos por trámite» permite seleccionar una carrera asignada y uno de los tres trámites: reconocimiento de malla a malla, homologación entre carreras de la UEB y homologación desde otra institución. Cada combinación tiene su propia lista de documentos, indicaciones, obligatoriedad y disponibilidad.

Rutas relativas a `/api/v1`:

| Método | Ruta | Función |
| --- | --- | --- |
| GET | `/coordinator/requirements` | Carreras propias, trámites, requisitos específicos y requisitos institucionales activos |
| POST | `/coordinator/requirements` | Crear un requisito de una carrera propia |
| PUT | `/coordinator/requirements/{id}` | Editar o desactivar un requisito de una carrera propia |
| DELETE | `/coordinator/requirements/{id}` | Eliminar un requisito sin expedientes asociados |

Crear/editar envía `carrera_id`, `tramite_proceso_id`, `nombre` (hasta 150 caracteres), `descripcion` (opcional, hasta 2000), `obligatorio` y `activa`. Los requisitos institucionales (`carrera_id=null`) siguen bajo control administrativo. Se rechazan nombres repetidos para la misma carrera y trámite, incluyendo requisitos institucionales activos, sin distinguir mayúsculas ni espacios exteriores. Un requisito usado no puede eliminarse ni trasladarse a otra carrera o trámite; puede desactivarse para nuevas solicitudes.

`GET /student/catalogo` incluye `requisitos` activos de las carreras asignadas y los generales. El formulario muestra una vista previa según la carrera y trámite elegidos y requiere al menos un documento obligatorio. La creación conserva nombre, indicaciones y obligatoriedad en el checklist del expediente; editar la configuración no altera solicitudes existentes.

Si el coordinador ya clasificó el antecedente de origen, la institución se toma automáticamente de ese registro al crear una solicitud y no se pide repetirla. El servidor también impide modificarla desde una solicitud pendiente y devuelve `puede_editar_procedencia=false`. Los clientes anteriores conservan la edición manual cuando el origen no está clasificado. No hace falta una migración adicional: esta función reutiliza `documentos_requeridos_proceso` y las copias de requisitos existentes.

Prueba manual: entrar como coordinador, configurar un documento obligatorio para cada trámite de una carrera propia, entrar como estudiante y comprobar la vista previa y el checklist. Después, editar o desactivar el requisito y verificar que la solicitud anterior conserva sus instrucciones; una nueva consulta del catálogo debe mostrar la configuración vigente. Intentar editar una carrera ajena o un requisito institucional debe ser rechazado.
