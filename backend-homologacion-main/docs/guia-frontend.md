# Guía de integración para Frontend

El proyecto incluye Laravel 13 y Next.js 16. La referencia generada está en [openapi.json](openapi.json) y el contrato general en [api.md](api.md). Usar backend y frontend de la misma revisión.

## Conexión local

Backend: PostgreSQL, `composer install`, `.env` propio, `php artisan key:generate` solo en instalaciones nuevas, `php artisan migrate --seed`. Para la demostración ejecutar `php artisan db:seed --class=PresentialDemoSeeder` (solo local/testing). Conservar `.env` y `APP_KEY` al actualizar; no ejecutar `migrate:fresh` sobre datos que se quieran guardar.

Frontend: `npm ci`; `.env.local` con `NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1`. Backend `.env`: `FRONTEND_URL=http://localhost:3000,http://127.0.0.1:3000`. Luego `php artisan config:clear`, `php artisan serve --host=127.0.0.1 --port=8000` y, en otra terminal del frontend, `npm run dev`. El frontend nunca se conecta directamente a PostgreSQL.

Las peticiones JSON usan `Accept: application/json`, `Content-Type: application/json` y `Authorization: Bearer <token>`. El cliente compartido está en `lib/api.ts`. 401: limpiar token y volver al login; 403: revisar rol o cambio obligatorio; 404: recurso inexistente o fuera de alcance; 409: conflicto de estado/referencias; 422: mostrar `errors` junto al formulario.

## Recorrido para probar

1. Administrador: Usuarios → asignar carreras al coordinador; Catálogos académicos → facultades, carreras, modalidades y requisitos.
2. Coordinador: Estudiantes → crear/editar y asignar destino. Sus cuentas nuevas usan su identificación como contraseña inicial y deben cambiarla.
3. Estudiante: completar antecedentes, crear solicitud y entregar documentos presencialmente. Consultar checklist y observaciones.
4. Coordinador: Solicitudes → registrar entrega, validar u observar; recibir correcciones. La revisión avanza automáticamente.
5. Con documentación completa: mallas, comparaciones, resultado, informe técnico, Consejo y resolución. El estudiante descarga la resolución final.

La demostración incluye un expediente con dos de seis documentos validados y una observación. Las tres cuentas están indicadas en el README raíz. Los correos de desarrollo se registran localmente; para entrega real debe configurarse SMTP.

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
