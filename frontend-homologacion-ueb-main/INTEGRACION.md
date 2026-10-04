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

Las cuentas creadas por `POST /api/v1/admin/users` reciben una contraseña aleatoria por correo. No enviar `password` al crear o editar usuarios: se rechaza con 422. Las cuentas existentes conservan su acceso. La contraseña temporal caduca en 24 horas (`TEMPORARY_PASSWORD_HOURS`). Se almacena únicamente su hash; si el envío falla, la creación se revierte y puede reintentarse.

`POST /login` y `GET /me` devuelven `user.must_change_password`. Si es `true`, mostrar exclusivamente cambio de contraseña o cierre de sesión. El resto de la API devuelve 403 con `code: PASSWORD_CHANGE_REQUIRED`.

Rutas relativas a `/api/v1`:

| Método y ruta | Datos | Acceso |
| --- | --- | --- |
| `POST /change-password` | `current_password`, `password`, `password_confirmation` | Bearer, cuenta activa; permitido durante el primer ingreso |
| `POST /forgot-password` | `email` | Público, limitado; respuesta genérica para no revelar cuentas |
| `POST /reset-password` | `email`, `token`, `password`, `password_confirmation` | Público, limitado; token válido durante 60 minutos y de un solo uso |

La nueva contraseña requiere 12 caracteres como mínimo, mayúsculas, minúsculas y números. El cambio inicial exige una contraseña diferente. Al cambiar o recuperar la contraseña se revocan todos los tokens y sesiones; borrar el token local y volver al login. Recuperar la contraseña también permite activar una cuenta cuya contraseña temporal caducó. Las cuentas inactivas no reciben enlaces.

El enlace de recuperación abre el frontend con `?reset_token=...&email=...`. El frontend incluido ya implementa estas pantallas. `FRONTEND_URL` debe apuntar al frontend; si contiene varios orígenes, el primero se utiliza para los correos.

En desarrollo, `MAIL_MAILER=log` escribe los correos en `storage/logs/laravel.log`, **sin enviarlos a una bandeja real**. Para entrega real se requiere configurar el transporte SMTP en `.env`, limpiar la configuración y probar la recepción con una cuenta controlada. No publicar `.env` ni registros que contengan contraseñas temporales o enlaces.

Al actualizar ejecutar `php artisan migrate --no-interaction`. No usar `migrate:fresh` sobre datos que se quieran conservar. La migración añade `must_change_password` y `temporary_password_expires_at` sin reiniciar las contraseñas existentes.

## Gestión presencial y nuevos CRUD

La documentación se entrega físicamente. **Solo el coordinador valida el checklist**; el administrador configura los requisitos y el estudiante consulta el resultado. Los PDF históricos siguen disponibles mediante descarga privada, pero ya no se cargan ni reemplazan desde el estudiante. La carga de resoluciones y la generación del informe técnico PDF se conservan.

### Identificación y estudiantes

Seleccionar `tipo_identificacion` (`cedula` o `pasaporte`) antes de ingresar `cedula` (se conserva este nombre de campo por compatibilidad). Cédula: exactamente 10 dígitos. Pasaporte: 5–20 letras mayúsculas o números, sin espacios. Es validación de formato, no una consulta al Registro Civil. Ambos identificadores son únicos. El filtro `tipo_identificacion` se admite en usuarios administrativos y estudiantes del coordinador; `search` busca nombre, identificación o correo.

Rutas relativas a `/api/v1`:

| Método y ruta | Comportamiento |
| --- | --- |
| `GET /coordinator/students` | Listar y filtrar estudiantes dentro del alcance del coordinador. |
| `POST /coordinator/students` | Crear exclusivamente una cuenta de estudiante, enviar contraseña temporal y asignar destino. |
| `GET /coordinator/students/{id}` | Consultar estudiante y asignaciones visibles. |
| `PUT /coordinator/students/{id}` | Editar datos y destino de un estudiante actualmente asignado. |
| `DELETE /coordinator/students/{id}` | Desactivar conservando el historial; 409 si tiene solicitudes activas. |
| `PATCH /coordinator/students/{id}/status` | Activar/desactivar mediante `cuenta_activa`. |

Crear/editar requiere `nombres_completos`, `tipo_identificacion`, `cedula`, `email`, `numero_celular`, `carrera_id` y `modalidad_id`. Carrera de destino: una de las asignadas al coordinador. Modalidad: activa y asociada a esa carrera. No se puede cambiar el destino durante una solicitud activa. La carrera e institución de **origen** permanecen en los antecedentes académicos; pueden ser externas. No enviar contraseña ni rol desde este formulario.

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

Rutas retiradas (410 para recursos propios): `POST /student/solicitudes/{id}/enviar`, `POST /student/solicitudes/{id}/documentos/{documento}` y `POST /coordinator/documents/{id}/verification`. No construir controles de subida, reenvío o doble verificación. `puede_enviar` siempre es false.
