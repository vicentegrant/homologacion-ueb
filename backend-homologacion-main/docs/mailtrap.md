# Correo de acceso y recuperación con Mailtrap

El flujo actual de autenticación usa la identificación como contraseña inicial hasheada y exige un cambio fuerte. La [guía de autenticación](prueba-autenticacion.md) describe el contrato actualizado; el análisis histórico de esta página documenta la implementación anterior con contraseñas iniciales aleatorias.

## Alternativa: SMTP de Gmail

El comando `send-mail` ahora usa el mailer indicado por `MAIL_MAILER`, y acepta `--mailer=smtp` o `--mailer=mailtrap-sdk` para una prueba específica. Los correos de bienvenida, reenvío y recuperación ya usan el transporte predeterminado. No se requieren librerías adicionales para SMTP.

Para una cuenta Gmail que permita contraseñas de aplicación, activar la verificación en dos pasos y crear una contraseña para «Homologación UEB» desde [Contraseñas de aplicación](https://myaccount.google.com/apppasswords). Algunas cuentas institucionales o con Protección Avanzada no permiten esta opción; ver los [requisitos de Google](https://support.google.com/accounts/answer/185833?hl=es).

Editar `.env` sustituyendo los valores de ejemplo:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_URL=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=TU_CUENTA@gmail.com
MAIL_PASSWORD=TU_CONTRASENA_DE_APLICACION
MAIL_FROM_ADDRESS=TU_CUENTA@gmail.com
MAIL_FROM_NAME="Homologación UEB"
MAIL_TEST_TO=CORREO_DESTINATARIO_REAL
```

`MAIL_USERNAME` y `MAIL_FROM_ADDRESS` deben contener la misma cuenta Gmail. `MAIL_PASSWORD` debe ser la contraseña de aplicación completa, sin los espacios de presentación, no la contraseña habitual de Google. `MAIL_TEST_TO` es el buzón donde comprobarás la recepción. El host, puerto y esquema son valores literales; `MAIL_URL=null` evita que una URL SMTP anterior sustituya estos ajustes. Google documenta estos [ajustes de SMTP](https://support.google.com/a/answer/176600?hl=es).

Desde el backend:

```powershell
php artisan config:clear --no-interaction
php artisan send-mail --no-interaction
```

También se puede pasar un destinatario mediante `php artisan send-mail --mailer=smtp --to=correo-destinatario-real --no-interaction`. La aceptación del servidor no garantiza recepción: revisar entrada y spam. Tras esa prueba, crear un usuario y solicitar recuperación de contraseña para comprobar los dos correos de la aplicación. Reiniciar workers existentes si utilizan configuración anterior.

Se añadieron cinco pruebas de selección SMTP, remitente/destinatario, compatibilidad de notificaciones, autenticación fallida sin exponer credenciales y rechazo de mailers que solo escriben en logs. Las pruebas capturan el envío localmente; todavía hace falta una cuenta y contraseña de aplicación para validar la entrega real. La configuración local no se cambia hasta conocer el proveedor elegido.

## Envío real mediante el SDK y la API

El transporte `mailtrap-sdk` está integrado en `config/mail.php` y usa los valores de `config/services.php`. Al seleccionarlo como predeterminado, las notificaciones actuales de bienvenida, reenvío y recuperación usan la API sin cambiar sus vistas ni sus controles de seguridad. El proveedor del SDK se descubre automáticamente. La configuración SMTP local sigue activa hasta configurar y seleccionar la API.

Dependencias instaladas con:

```powershell
composer require railsware/mailtrap-php symfony/http-client nyholm/psr7
```

Completar en `.env` antes de enviar:

```dotenv
MAIL_MAILER=mailtrap-sdk
MAILTRAP_HOST=send.api.mailtrap.io
MAILTRAP_API_KEY=REEMPLAZAR_TOKEN_API
MAILTRAP_TEST_TO=REEMPLAZAR_DESTINATARIO
MAIL_FROM_ADDRESS=REEMPLAZAR_REMITENTE
MAIL_FROM_NAME="Homologación UEB"
```

- `REEMPLAZAR_TOKEN_API`: token de **Email Sending** con permiso de envío para el dominio seleccionado. Reemplaza `<YOUR_API_TOKEN>` del ejemplo; no es `MAIL_USERNAME`, `MAIL_PASSWORD` ni la contraseña de la cuenta Mailtrap.
- `REEMPLAZAR_DESTINATARIO`: dirección real de un buzón al que tengas acceso. Sustituye `frespunina@gmail.com` del ejemplo. También puedes pasar `--to=tu-direccion-real` al comando.
- `REEMPLAZAR_REMITENTE`: dirección del dominio que hayas verificado en Mailtrap. Sustituye `hello@demomailtrap.co` y el remitente local `homologacion@example.test`; este último es solo para sandbox. El dominio de demostración permite pruebas con restricciones de destinatario y no debe asumirse disponible para cualquier dirección.
- `MAIL_FROM_NAME`: nombre visible del remitente; se conserva el nombre del proyecto en lugar de `Mailtrap Test`.
- `MAILTRAP_HOST=send.api.mailtrap.io` y `MAIL_MAILER=mailtrap-sdk` son valores literales y no necesitan reemplazo. Las variables SMTP no se usan con este transporte.

Desde el backend:

```powershell
php artisan config:clear --no-interaction
php artisan send-mail --no-interaction
```

Para probar la API aunque SMTP esté seleccionado, ejecutar `php artisan send-mail --mailer=mailtrap-sdk --to=tu-direccion-real --no-interaction`. Envía un mensaje del proyecto con categoría `Integration Test`, valida remitente y destinatario y devuelve un código de error si falta el token o falla la entrega a la API. No muestra tokens ni respuestas crudas del proveedor. La aceptación por la API no garantiza la entrega al buzón: revisar los [registros de envío](https://mailtrap.io/sending/email_logs).

La integración sigue el [puente Laravel oficial del SDK](https://github.com/mailtrap/mailtrap-php/blob/main/src/Bridge/Laravel/README.md) y la [guía de Email API/SMTP](https://docs.mailtrap.io/getting-started/email-api-smtp.md). Nunca guardar tokens reales en `.env.example` ni en el repositorio. No se ha enviado un correo real con la API: falta proporcionar el token, remitente verificado y destinatario. Las pruebas simulan únicamente la respuesta remota y ejercitan el transporte real del SDK, el comando y las notificaciones existentes.

Verificación de esta ampliación: **162 pruebas aprobadas, 992 aserciones**; Pint, validación de Composer y comprobación de diff aprobados. Se añadieron seis pruebas de la API/comando y una de creación de usuarios con el transporte seleccionado. `composer audit` informó dos avisos previos de seguridad en `league/commonmark` (GHSA-97jj-33gv-5xf9 y GHSA-3q6v-r5mr-hxv8); la instalación del SDK no cambió la versión de ese paquete.

## Análisis inicial

La aplicación ya tenía Password Broker, `password_reset_tokens`, expiración de 60 minutos, throttle de recuperación/restablecimiento, respuesta genérica, contraseña temporal aleatoria hasheada y middleware `password.changed`. El frontend tenía solicitud, restablecimiento y cambio inicial. La línea base fue de 143 pruebas aprobadas.

Faltaban la columna `password_temporal`, el correo de recuperación en español, la ruta `/restablecer-contrasena`, manejo de fallos de correo sin revertir cuentas, reenvío de credenciales y continuación al panel tras el cambio inicial. La notificación de credenciales existente era síncrona; se conservó ese modo para informar inmediatamente el resultado sin almacenar contraseñas en trabajos de cola.

## Obtener las credenciales

1. Iniciar sesión en Mailtrap.
2. Abrir **Email Testing → Inboxes**. Según la versión de la interfaz puede aparecer como **Email Sandbox**.
3. Crear o seleccionar el inbox/sandbox de desarrollo.
4. Abrir **SMTP Settings**, o **Integration → SMTP** en la interfaz actual.
5. Copiar Host, Port, Username y Password. Usuario y contraseña son credenciales SMTP del inbox, no el correo ni la contraseña de acceso a Mailtrap.
6. Completar las variables en `backend-homologacion-main/.env` y guardar. No compartir credenciales en el chat ni en Git.

El host del sandbox es `sandbox.smtp.mailtrap.io`; se puede utilizar el puerto 2525. La integración se describe en la [documentación oficial de Mailtrap](https://docs.mailtrap.io/email-sandbox/setup/sandbox-smtp-integration). Los mensajes de prueba se capturan en el sandbox; no llegan al buzón real del destinatario.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=usuario_smtp_del_inbox
MAIL_PASSWORD=contrasena_smtp_del_inbox
MAIL_FROM_ADDRESS="homologacion@example.test"
MAIL_FROM_NAME="Homologación UEB"
FRONTEND_URL=http://localhost:3000,http://127.0.0.1:3000
```

Los valores de usuario y contraseña anteriores son marcadores de posición. El `.env.example` incluye las variables SMTP con ambos campos vacíos. Se actualizó la configuración SMTP local conservando los campos de credenciales para que el usuario los complete. Solo se conserva una declaración de `FRONTEND_URL` en el ejemplo; el primer origen se usa en enlaces.

Después de editar `.env`:

```powershell
php artisan config:clear --no-interaction
```

## Comportamiento y API

- `POST /api/v1/forgot-password`: recibe `email`. Siempre devuelve el mismo mensaje genérico para una dirección válida, incluso si no existe la cuenta o falla SMTP.
- `POST /api/v1/reset-password`: recibe `email`, `token`, `password` y `password_confirmation`. Requiere token válido de un solo uso y contraseña de mínimo 8 caracteres con letras y números. El correo abre `FRONTEND_URL/restablecer-contrasena?token=...&email=...`.
- Crear desde administrador o coordinador genera una contraseña segura, la guarda únicamente hasheada y marca `password_temporal=true`. Devuelve 201 incluso si falla el envío, con `credentials_email_sent=false` y mensaje para reintentar.
- `POST /api/v1/admin/users/{user}/credentials`: reenvío por el administrador creador.
- `POST /api/v1/coordinator/students/{student}/credentials`: reenvío por el coordinador creador, exclusivamente para estudiantes de su alcance.
- El reenvío genera otra contraseña temporal, invalida tokens, sesiones y enlaces de restablecimiento anteriores y exige cambio en el siguiente ingreso. Solo el creador puede actuar; cuenta inactiva devuelve 409 y creador distinto devuelve 403. Tiene throttle de 5 peticiones por minuto.
- Si falla el reenvío, devuelve `credentials_email_sent=false`; la nueva contraseña ya reemplazó la anterior. Puede reenviarse de nuevo o utilizar recuperación.
- Login y `/me` devuelven `password_temporal` y el alias compatible `must_change_password`. Ambos se sincronizan desde el modelo. Con el indicador activo solo están disponibles `/me`, `/logout` y `/change-password`; las demás rutas protegidas responden 403 con `PASSWORD_CHANGE_REQUIRED`.
- El cambio inicial exige contraseña actual correcta, nueva contraseña distinta y confirmación. Revoca los accesos anteriores; el frontend obtiene una sesión nueva automáticamente y continúa al panel del rol. Si esa sesión no puede obtenerse, informa que el cambio se completó y permite volver al login.

Los correos de bienvenida y recuperación usan vistas HTML en español. Los errores de transporte se registran únicamente con el ID del usuario y el tipo de excepción, sin contenido SMTP, credenciales ni contraseñas. El envío de credenciales se bloquea con mailers `log`/`failover` para evitar registrar el contenido sensible. Ninguna contraseña temporal se devuelve en la API ni se guarda en una cola.

## Migración y usuarios existentes

Se añadió una migración reversible para `users.password_temporal`. Copia el indicador anterior; no modifica contraseñas. El rollback copia el estado de vuelta al indicador anterior antes de retirar la nueva columna. `password_reset_tokens` ya existía. La migración se aplicó con `php artisan migrate --no-interaction` y pasó una prueba de ida y vuelta en la base exclusiva de pruebas.

UserFactory y DatabaseSeeder mantienen `password`. No se modificaron seeders ni reiniciaron cuentas existentes. Los demás seeders de demostración conservan sus contraseñas previas.

## Archivos de esta implementación

Backend, rutas relativas a su raíz:

- Nuevos: `app/Http/Controllers/Api/CredentialsController.php`, `app/Notifications/ResetPasswordNotification.php`, `resources/views/mail/reset-password.blade.php`, `resources/views/mail/temporary-password.blade.php`, `database/migrations/2026_10_04_154632_add_password_temporal_to_users_table.php`, `tests/Feature/EmailCredentialsTest.php`, `docs/mailtrap.md`.
- Modificados: `.env.example`, `config/mail.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `app/Notifications/TemporaryPasswordNotification.php`, `app/Services/UserService.php`, `app/Http/Controllers/Api/AuthController.php`, `app/Http/Controllers/Api/Admin/UserController.php`, `app/Http/Controllers/Api/Coordinator/StudentController.php`, `app/Http/Resources/Api/StudentResource.php`, `routes/api.php`.
- Actualizados: `tests/Feature/PasswordLifecycleTest.php`, `tests/Feature/RequirementCompletionTest.php` (referencia a la notificación personalizada), `docs/api.md`, `docs/guia-frontend.md`, `docs/openapi.json`.
- Configuración local: `.env`, excluido de Git; no se imprimieron sus credenciales SMTP.

Frontend, rutas relativas a su raíz:

- Nuevo: `app/restablecer-contrasena/page.tsx`.
- Modificados: `app/page.tsx`, `lib/api.ts`, `views/password.tsx`, `views/admin/usuarios.tsx`, `views/coordinator/estudiantes.tsx`, `INTEGRACION.md`.

## Verificación y comandos

Se ejecutaron pruebas por bloque, generación del contrato OpenAPI sin tipos desconocidos, Pint, TypeScript separado y compilación Next.js. La compilación incluye la ruta real de restablecimiento. La suite nueva cubre fallos SMTP al crear desde ambos roles, reenvío, autorización, revocación, protección contra logs, textos de correo, bloqueo, cambio inicial, token inválido y reversibilidad. La suite previa conserva expiración, uso único, correo inexistente, restablecimiento por los tres roles y generación de credenciales con `Notification::fake`.

Resultado final: **155 pruebas aprobadas, 954 aserciones**. Las 12 pruebas de `EmailCredentialsTest` pasan. TypeScript, compilación, Pint, exportación OpenAPI y comprobación de diff pasan. La migración nueva figura aplicada en lote 3 y las dos rutas de reenvío aparecen en `route:list`.

Comandos desde el backend, en orden para actualizar y verificar:

```powershell
php artisan config:clear --no-interaction
php artisan migrate --no-interaction
php vendor/bin/pint --dirty --format agent
php artisan route:list --path=api --except-vendor --no-interaction
php artisan test --compact
php artisan scramble:export --path=docs/openapi.json --fail-on-unknown --no-interaction
Set-Location ..\frontend-homologacion-ueb-main
node node_modules/typescript/bin/tsc --noEmit
npm.cmd run build
```

No se necesita un worker de cola para estos dos correos; otras notificaciones del proyecto mantienen su configuración previa. No se ejecutó `migrate:fresh` sobre la base de desarrollo.

## Prueba manual con Mailtrap

1. Completar las credenciales del inbox en `.env`, limpiar configuración y arrancar backend/frontend siguiendo el README.
2. Entrar como coordinador y crear un estudiante de prueba con correo único, cédula/celular válidos y datos académicos completos.
3. Comprobar `credentials_email_sent=true`; abrir el correo de bienvenida en Mailtrap y verificar nombre, correo, contraseña temporal, URL de acceso e instrucción de cambio.
4. Iniciar sesión con esa contraseña. Debe aparecer únicamente la pantalla obligatoria de cambio, incluso cambiando la ruta manualmente. La API debe bloquear las otras acciones.
5. Cambiar la contraseña por una diferente que cumpla las reglas; debe abrirse automáticamente el panel del estudiante. Cerrar sesión.
6. Seleccionar «Olvidé mi contraseña», solicitar enlace y abrir el segundo correo en Mailtrap.
7. Seguir el enlace a `/restablecer-contrasena`, restablecer e iniciar sesión con la nueva contraseña. Probar que el enlace ya utilizado y la contraseña anterior dejan de servir.
8. Como creador, pulsar «Reenviar credenciales» y confirmar. Debe aparecer otro correo, revocarse la sesión anterior y exigirse cambio otra vez. Un usuario del mismo rol que no sea el creador no puede reenviar.
9. Para probar fallo de SMTP en desarrollo, utilizar temporalmente un puerto inválido, limpiar configuración y crear otra cuenta: debe conservarse con aviso de fallo. Restaurar el puerto y reenviar desde su creador.

La entrega SMTP real y el recorrido en navegador están pendientes de credenciales y verificación manual; las pruebas automatizadas simulan el canal de correo y comprueban el comportamiento HTTP.

## Fuera de alcance

Se conservaron los cambios académicos de la tarea anterior, las otras notificaciones, dependencias, seeders y políticas ajenas al correo. El proyecto omite comprobación de tipos durante `next build`; por eso se ejecutó TypeScript por separado. La configuración histórica de base de datos del ejemplo no se alteró en esta tarea.
