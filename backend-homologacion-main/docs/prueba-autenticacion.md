# Prueba de autenticación y cambio obligatorio

## Preparar y reiniciar las cuentas

El backend usa Laravel 13, PostgreSQL y Sanctum con tokens Bearer. El frontend conserva Next.js y el formulario existente. Solo se agrega `users.password_changed_at`; `must_change_password` ya existe y se mantiene sincronizado con `password_temporal` por compatibilidad. La migración no cambia las contraseñas de cuentas existentes.

Desde PowerShell:

```powershell
cd C:\PROYECTOS\homologacion-ueb\backend-homologacion-main
php artisan migrate --no-interaction
php artisan auth:reset-demo --no-interaction
```

El comando llama a `DevelopmentAuthSeeder`. Ambos están restringidos a `APP_ENV=local` o `testing`, incluso si se intenta ejecutar el seeder con `--force` en producción. No se incluye automáticamente en `DatabaseSeeder`. Puede ejecutarse también directamente:

```powershell
php artisan db:seed --class=DevelopmentAuthSeeder --no-interaction
```

Repetir `auth:reset-demo` restablece únicamente las tres cuentas de la tabla: identificación como contraseña hasheada, cambio obligatorio activo y fecha de cambio vacía. Revoca sus tokens, sesiones y enlaces de recuperación; conserva sus IDs y las demás cuentas. No envía correos, elimina solicitudes ni reinicia la base de datos. No hace falta configurar Gmail ni Mailtrap para probar este flujo.

| Rol | Correo (usuario) | Contraseña inicial (cédula) | Nueva contraseña sugerida |
| --- | --- | --- | --- |
| Administrador | administrador.auth@example.test | 0100000009 | AdminUeb2026! |
| Coordinador | coordinador.auth@example.test | 0200000008 | CoordUeb2026! |
| Estudiante | estudiante.auth@example.test | 0300000007 | EstudUeb2026! |

Son datos ficticios exclusivos de desarrollo. Mantener los ceros iniciales. El proyecto valida cédulas de exactamente 10 dígitos y conserva el soporte previo de pasaporte (5–20 letras mayúsculas o números); el campo se sigue llamando `cedula`. No consulta identidad real ni el Registro Civil. Las cuentas con pasaporte usan ese identificador validado como contraseña inicial.

## Arrancar la aplicación

En una terminal del backend:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

En otra terminal:

```powershell
cd C:\PROYECTOS\homologacion-ueb\frontend-homologacion-ueb-main
npm.cmd run dev
```

Abrir el origen que muestre Next.js en la terminal. El `.env.local` del frontend debe usar `NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1`. Si el puerto del backend cambia, ajustar esa URL.

## Recorrido manual para cada rol

Realizar todos estos pasos primero con Administrador, luego con Coordinador y finalmente con Estudiante, usando la fila correspondiente de la tabla:

1. Cerrar cualquier sesión anterior. Iniciar sesión con el correo y la cédula ficticia. La respuesta de login debe incluir `must_change_password=true` y un token nuevo. El frontend debe mostrar únicamente **Cambiar contraseña** y **Cerrar sesión**, sin menú del panel.
2. Recargar la página y manipular el hash de navegación: debe mantenerse el formulario obligatorio. El frontend conserva únicamente los datos necesarios de la respuesta de login para restaurar ese formulario; no obtiene permisos desde esa caché.
3. Con el token de login, intentar las rutas de la tabla siguiente mediante Postman o las herramientas de desarrollo. Todas deben responder **403**, con `code=PASSWORD_CHANGE_REQUIRED`, incluido `/me`. `POST /logout` sí debe funcionar; volver a iniciar sesión para continuar.
4. Probar la cédula como nueva contraseña y como confirmación: debe rechazarse. Probar `abc123`, o `Abcdef12` sin símbolo: debe rechazarse. Probar la contraseña sugerida con confirmación distinta: debe rechazarse. La cuenta debe seguir pendiente y su contraseña inicial debe seguir funcionando.
5. Introducir la cédula en **Contraseña actual** y la contraseña sugerida idéntica en **Nueva contraseña** y **Confirmar contraseña**. Debe completar el cambio y abrir el panel correspondiente al rol, sin pedir un segundo login.
6. Comprobar que `/change-password` entrega un token nuevo. Con ese token, `/me` devuelve ambos indicadores en `false` y `password_changed_at` con fecha. La cédula deja de autenticar y los tokens previos reciben **401**. Los enlaces de recuperación anteriores tampoco sirven.
7. Abrir la ruta propia de la tabla con el token nuevo: debe responder **200**. Intentar las dos rutas de otros roles: deben responder **403**. El administrador tampoco puede usar rutas de estudiante o coordinador.
8. Cerrar sesión y entrar nuevamente con la contraseña sugerida: debe acceder directamente a su panel. Para repetir todo desde el principio, ejecutar `auth:reset-demo`, cerrar/recargar la sesión del navegador y usar nuevamente la cédula.

| Rol | Ruta propia para comprobar 200 | Rutas ajenas para comprobar 403 |
| --- | --- | --- |
| Administrador | GET /api/v1/admin/dashboard | GET /api/v1/coordinator/students y GET /api/v1/student/profile |
| Coordinador | GET /api/v1/coordinator/students | GET /api/v1/admin/dashboard y GET /api/v1/student/profile |
| Estudiante | GET /api/v1/student/profile | GET /api/v1/admin/dashboard y GET /api/v1/coordinator/students |

Estas tres cuentas prueban autenticación y permisos; el coordinador inicialmente no tiene carreras asignadas y su lista puede estar vacía. Los seeders académicos anteriores conservan sus datos; al crear nuevas cuentas de demostración también exigen el cambio inicial, sin reemplazar contraseñas de cuentas que ya existían.

Login tiene límite de 10 solicitudes por minuto por IP; cambio y recuperación, 5 por minuto. Si se alcanza el límite durante las pruebas, esperar al menos un minuto. Una cuenta inexistente, una contraseña incorrecta, una cuenta inactiva o una credencial temporal de reenvío caducada reciben el mismo mensaje de login: **Credenciales incorrectas.**, HTTP 401.

## Prueba de API en PowerShell

Ejemplo para estudiante. Reemplazar correo, cédula y nueva contraseña por la fila correspondiente para probar otros roles. La base corresponde al comando de arranque anterior:

```powershell
$apiBase = 'http://127.0.0.1:8000/api/v1'
$loginBody = @{ email = 'estudiante.auth@example.test'; password = '0300000007' } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$apiBase/login" -ContentType 'application/json' -Body $loginBody
$headers = @{ Authorization = "Bearer $($login.token)"; Accept = 'application/json' }

# Antes del cambio, esta petición debe fallar con HTTP 403.
Invoke-RestMethod -Uri "$apiBase/student/profile" -Headers $headers

$changeBody = @{ current_password = '0300000007'; password = 'EstudUeb2026!'; password_confirmation = 'EstudUeb2026!' } | ConvertTo-Json
$change = Invoke-RestMethod -Method Post -Uri "$apiBase/change-password" -Headers $headers -ContentType 'application/json' -Body $changeBody
$headers.Authorization = "Bearer $($change.token)"

# Ahora devuelve 200 y el indicador must_change_password=false.
Invoke-RestMethod -Uri "$apiBase/me" -Headers $headers
Invoke-RestMethod -Uri "$apiBase/student/profile" -Headers $headers

# Estas peticiones deben fallar con HTTP 403 por pertenecer a otros roles.
Invoke-RestMethod -Uri "$apiBase/admin/dashboard" -Headers $headers
Invoke-RestMethod -Uri "$apiBase/coordinator/students" -Headers $headers
```

## Implementación y archivos

Rutas relativas al backend, salvo donde se indica frontend:

| Responsabilidad | Archivo |
| --- | --- |
| Migración reversible de fecha | database/migrations/2026_10_04_170216_add_password_changed_at_to_users_table.php |
| Cast de fecha y sincronización de indicadores existente | app/Models/User.php |
| Creación con identificación validada y hash | app/Services/UserService.php |
| Validación de cédula/pasaporte reutilizada | app/Rules/IdentificationNumber.php y los Form Requests existentes de administrador/coordinador |
| Login genérico y token nuevo | app/Http/Controllers/Api/AuthController.php |
| Cambio, recuperación, validaciones y rotación del token | app/Http/Controllers/Api/PasswordController.php |
| Bloqueo obligatorio | app/Http/Middleware/EnsurePasswordChanged.php |
| Permisos por rol existentes | app/Http/Middleware/EnsureUserHasRole.php |
| Rutas, excepciones del bloqueo y límites | routes/api.php; aliases existentes en bootstrap/app.php |
| Seeder de tres cuentas y reinicio | database/seeders/DevelopmentAuthSeeder.php y routes/console.php |
| Creación coherente en seeders existentes | database/seeders/AdminUserSeeder.php, DatabaseSeeder.php, StudentDemoSeeder.php y PresentialDemoSeeder.php |
| Notificación de credenciales sin caducidad ficticia | app/Notifications/TemporaryPasswordNotification.php y resources/views/mail/temporary-password.blade.php |
| Formulario y sustitución del token | frontend: views/password.tsx y lib/api.ts |
| Redirección obligatoria y panel por rol existente | frontend: app/page.tsx |
| Textos de creación coherentes | frontend: views/admin/usuarios.tsx y views/coordinator/estudiantes.tsx |
| Pruebas nuevas de flujo, validaciones, roles, límites, seeder y migración | tests/Feature/ForcedPasswordFlowTest.php |
| Pruebas existentes adaptadas al contrato nuevo | PasswordLifecycleTest.php, EmailCredentialsTest.php, ApiAuthenticationTest.php, RequirementCompletionTest.php, EndToEndHomologacionTest.php y Student/StudentDemoSeederTest.php dentro de tests/Feature |

El reenvío de credenciales sigue generando una contraseña aleatoria con caducidad para recuperar acceso; no vuelve a una cédula predecible en una cuenta existente. El registro público continúa deshabilitado. No se añadieron librerías, roles ni tablas de autenticación duplicadas.

## Verificación automatizada

```powershell
php vendor/bin/pint --dirty --format agent
php artisan test --compact
php artisan scramble:export --path=docs/openapi.json --fail-on-unknown --no-interaction
git diff --check
cd ..\frontend-homologacion-ueb-main
node node_modules/typescript/bin/tsc --noEmit
npm.cmd run build
```

Las 22 pruebas nuevas incluyen flujo completo de cada rol, rechazo de todas las clases de contraseñas débiles y confirmación, identificación inválida, conservación de cuentas ajenas al reiniciar, revocación, bloqueo de producción, límite de login, emisión de tokens distintos y reversibilidad de la migración. La recuperación tampoco permite reutilizar la contraseña actual. Las pruebas de API no sustituyen la comprobación visual en navegador de los pasos anteriores.

Resultado: **192 pruebas aprobadas, 1241 aserciones**. Pint, exportación OpenAPI sin tipos desconocidos, TypeScript separado, compilación Next.js y comprobación de diff aprobados. La migración figura aplicada en lote 4 y las tres cuentas están creadas en la base local con cambio obligatorio pendiente. No se realizó el recorrido visual en navegador.
