# Conexión con el backend de homologación

1. Backend (carpeta `backend-homologacion-main`):
   - En `.env`: `FRONTEND_URL=http://localhost:3000,http://127.0.0.1:3000`
   - `php artisan config:clear` y `php artisan serve` (queda en http://127.0.0.1:8000)
   - Cuentas: `php artisan db:seed --class=AdminUserSeeder` (requiere `INITIAL_ADMIN_*`)
     y, para pruebas, `php artisan db:seed --class=PresentialDemoSeeder` (cuentas y contraseña en el README raíz).
2. Frontend (esta carpeta):
   - `cp .env.example .env.local` y ajustar `NEXT_PUBLIC_API_URL` si el backend no está en 127.0.0.1:8000.
   - `pnpm install` (o `npm install`) y `pnpm dev` → http://localhost:3000
3. Abrir el frontend con el mismo host que está en `FRONTEND_URL` (localhost y 127.0.0.1 son orígenes distintos).

Todo el acceso a la API pasa por `lib/api.ts` (`api`, `upload`, `download`, `loginRequest`, `getCurrentUser`, `logoutRequest`).
Un 401 en cualquier petición borra el token y regresa al login.

## Navegación (rutas por hash)

| Rol | Rutas |
| --- | --- |
| Estudiante | `#/solicitudes`, `#/solicitudes/:id`, `#/perfil`, `#/notificaciones` |
| Coordinador | `#/solicitudes`, `#/solicitudes/:id`, `#/estudiantes`, `#/mallas`, `#/mallas/:id` |
| Administrador | `#/usuarios`, `#/catalogos`, `#/solicitudes`, `#/solicitudes/:id`, `#/estudiantes` |

Estructura: `components/app` (shell, UI común), `views/<rol>` (pantallas), `lib/api.ts` (cliente), `lib/router.ts` (rutas).


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
## Requisitos configurados por el coordinador

La interfaz carga las secciones bajo demanda y conserva el contenido durante las actualizaciones. El cliente comparte peticiones GET simultáneas y guarda sus respuestas durante 15 segundos en memoria, por token y URL; no guarda errores y siempre consulta `/me` al servidor. Guardar, subir archivos, actualizar manualmente o cambiar de sesión invalida esas lecturas. Una respuesta anterior no reemplaza una carga más reciente. Las búsquedas de estudiantes esperan 250 ms antes de consultar. La revisión de documentos actualiza documentos y expediente sin desmontar el análisis académico. Sus asignaturas se consultan con `GET /coordinator/curricula?include_subjects=1`, recorriendo la paginación sin pedir cada detalle por separado.

Comprobación manual de rendimiento: en la pestaña Network del navegador, filtrar Fetch/XHR, guardar un registro y verificar que no aparece una nueva petición de tipo Document. Revisar un documento conservando un formulario del análisis abierto: debe conservar sus valores y solo consultar documentos y expediente (si cambia el estado, se actualizan las secciones dependientes). Cambiar de usuario y comprobar que los catálogos corresponden a la nueva sesión. Las pruebas del cliente se ejecutan con `npm test` en Node.js 24; para usar la compilación optimizada ejecutar `npm run build` y después `npm run start`.

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

El coordinador puede adjuntar un PDF de ejemplo opcional de hasta 2 MB al crear o editar un requisito. Se envía como archivo multipart en `ejemplo`; para actualizar con archivo se utiliza `POST /coordinator/requirements/{id}` con `_method=PUT`. La edición sin archivo conserva el actual. `eliminar_ejemplo=true` lo retira; no puede combinarse con una subida. Se valida el contenido PDF, la extensión y el tamaño. Los archivos se guardan en el disco privado local usando el campo existente `ruta_ejemplo`, sin migración ni enlace público de almacenamiento.

Cada requisito incluye `ejemplo_download_url` (o null); los documentos del checklist del estudiante también lo incluyen dentro de `requisito`. `GET /coordinator/requirements/{id}/example` permite descargar ejemplos de carreras propias e institucionales activos. `GET /student/requirements/{id}/example` permite ejemplos de requisitos activos de sus carreras o institucionales y de requisitos ya presentes en sus expedientes. Requieren Bearer, cuenta activa, cambio de contraseña completado y el rol de la ruta. Recursos ajenos o sin archivo devuelven 404. Reemplazar o retirar el PDF actualiza la referencia disponible también para expedientes existentes, sin modificar sus documentos presentados, nombres ni instrucciones. La sustitución borra el archivo anterior después de guardar; si falla la escritura, el anterior se conserva y la subida nueva se elimina.

Prueba manual del PDF: crear un requisito con un PDF de menos de 2 MB, guardar y descargarlo desde la lista del coordinador. Entrar como estudiante de esa carrera, elegir el trámite y descargarlo desde la vista previa; crear una solicitud y comprobar el botón «PDF de ejemplo» en su checklist. Editar el requisito sin nuevo archivo conserva el PDF; adjuntar otro lo reemplaza y marcar ?Retirar el PDF al guardar? lo elimina. Comprobar el rechazo de archivos no PDF, mayores de 2 MB y solicitudes de otra carrera. El límite coincide con el PHP local actual (`upload_max_filesize=2M`).
