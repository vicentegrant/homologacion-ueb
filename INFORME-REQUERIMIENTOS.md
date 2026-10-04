# Implementación de requerimientos — 4 de octubre de 2026

Se completaron los cambios de aplicación posibles con el esquema actual. Tras la indicación de omitir la base de datos, no se cambiaron migraciones, modelos, seeders ni configuración de conexión, y no se ejecutaron comandos de migración o seeding manuales. Se conservaron los nombres `tipo_identificacion` y `must_change_password`.

## Análisis inicial y resultado

| Requerimiento | Existía | Resultado |
| --- | --- | --- |
| Migraciones y relaciones | Facultades, modalidades, carrera con una facultad y varias modalidades | Omitido por la nueva indicación: varias facultades, ciclos, columnas de autenticación solicitadas y eliminación de carga horaria |
| Seeders | Roles, cuenta `test@example.com`, administrador configurado y demostraciones opcionales | Conservados; no se ejecutaron ni alteraron seeders manualmente |
| CRUD administrativo | Catálogos académicos y autorización por administrador | Conservado; corregido el error 500 al eliminar facultades/modalidades con referencias en PostgreSQL |
| Eliminar usuarios | Activación/desactivación, sin ruta de eliminación administrativa | Nueva ruta DELETE, confirmación en interfaz, bloqueo de autoeliminación y protección de registros académicos |
| Coordinador | Crear/editar estudiantes, destino y modalidad, aislamiento por carreras, rechazo de roles y contraseñas proporcionadas | Agregada selección de origen y sus antecedentes; se conservó la desactivación como comportamiento de DELETE |
| Contraseña temporal | Generación aleatoria, correo, caducidad y cambio obligatorio | Conservado; nueva contraseña acepta mínimo 8 caracteres con letras y números |
| Tipo de institución | Columna existente, campo libre en API y cuatro opciones en interfaz | Valores permitidos: `publica`, `privada`, `instituto`; interfaz muestra Universidad Pública, Universidad Privada e Instituto |
| Períodos cursados | Columna y campo de texto existentes | Valida exactamente cuatro dígitos o dos grupos de cuatro separados por guion: `2025`, `2024-2025` |
| Subida del estudiante | Interfaz ya presencial; ruta deshabilitada con 410 y servicio antiguo todavía disponible | Eliminados ruta, método de subida, FormRequest y servicio; ruta inexistente responde 404; archivos históricos conservados |
| Recuperación | Password Broker, notificación nativa, token de 60 minutos, throttle, mensaje genérico y pantallas | Conservado y probado para los tres roles; ajustada regla de contraseña y documentación |
| Celulares | Admitían de 7 a 20 caracteres | API y formularios correspondientes exigen 10 dígitos |

El origen se elige de carreras existentes y debe ser diferente del destino. Al no crear columnas, se guarda su nombre en `antecedentes_academicos.carrera_origen`, junto con institución, tipo y período. La edición del coordinador actualiza el antecedente más reciente; si no hay ninguno, lo crea. El destino permanece asociado mediante `estudiante_carreras`. Cambiar carreras durante una solicitud activa devuelve 409. Se solicita también la información de antecedentes al registrar/editar para completar las columnas obligatorias existentes.

El administrador elimina cuentas sin historial académico. Si existen solicitudes, antecedentes, mallas, auditoría o referencias que impidan la eliminación, devuelve 409 y sugiere desactivar. La eliminación es transaccional: limpia asignaciones de coordinación vacías, tokens, sesiones, notificaciones y tokens de recuperación; las relaciones restantes siguen las restricciones existentes. Si una relación bloquea la eliminación, toda la operación se revierte. El administrador no puede eliminarse a sí mismo.

## Verificación ejecutada

- Primer intento de suite: 126 pruebas; 5 pasaban y 121 errores porque faltaba `homologacion_ueb_test`. Se creó esa base exclusiva antes de la indicación posterior de omitir trabajo de base de datos; la base de desarrollo no se vació.
- Línea base posterior: 124/126. Los dos fallos provenían de restricciones PostgreSQL con SQLSTATE `23001`, mientras el código y una prueba solo contemplaban `23503`.
- Corrección inicial: 126/126; no se cambiaron restricciones ni migraciones. La prueba de restricción ahora acepta los dos códigos que identifican la referencia bloqueada.
- Bloques sucesivos: validaciones y recuperación 21/21; eliminación administrativa 19/19; origen/destino y celulares 37/37; estudiante y gestión presencial 55/55.
- Verificación final: **143/143 pruebas; 890 aserciones**. Incluye 17 casos nuevos, recuperación de todos los roles con `Notification::fake`, contraseñas débiles, cambio inicial, validaciones de antecedentes, origen/destino, celulares, autorización, eliminación y reversión ante referencias.
- `php artisan route:list --path=api --except-vendor --no-interaction`: correcto, 85 rutas; presente DELETE administrativo y ausente POST de subida del estudiante.
- `php artisan scramble:export --path=docs/openapi.json --fail-on-unknown --no-interaction`: correcto.
- `php vendor/bin/pint --dirty --format agent`: correcto.
- TypeScript con `--noEmit`: correcto. Se ejecutó separado porque la compilación del proyecto omite la comprobación de tipos.
- `npm.cmd run build`: correcto.
- `git diff --check`: correcto.
- `migrate:fresh --seed` y `migrate:rollback`: omitidos conforme a la indicación posterior.

Las pruebas que usaban el servicio de subida ahora preparan archivos históricos directamente en el almacenamiento falso. Las expectativas de la ruta retirada se actualizaron de 410 a 404; se mantuvo cobertura de privacidad, conservación de archivos y descarga. El ejemplo del test de extremo a extremo se ajustó de `pública` al valor canónico `publica`.

No se ejecutó una sesión manual de navegador ni se certificó su consola. Las pruebas HTTP automatizadas y la compilación pasan. La recepción real de correo SMTP tampoco se probó; la suite simula las notificaciones.

## Archivos

Rutas relativas a `backend-homologacion-main`:

- Modificados: `app/Http/Controllers/Api/Admin/CatalogManagementController.php`, `app/Http/Controllers/Api/Admin/UserController.php`, `app/Http/Controllers/Api/Coordinator/CatalogController.php`, `app/Http/Controllers/Api/PasswordController.php`, `app/Http/Controllers/Api/Student/DocumentController.php`.
- Modificados: `app/Http/Requests/Api/Admin/StoreAdminUserRequest.php`, `app/Http/Requests/Api/Admin/UpdateAdminUserRequest.php`, `app/Http/Requests/Api/Coordinator/SaveStudentRequest.php`, `app/Http/Requests/Api/Student/StoreAcademicBackgroundRequest.php`, `app/Http/Requests/Api/Student/UpdateAcademicBackgroundRequest.php`, `app/Http/Requests/Api/Student/UpdateProfileRequest.php`.
- Modificados: `app/Services/CoordinatorStudentService.php`, `app/Services/UserService.php`, `routes/api.php`.
- Eliminados: `app/Services/StudentDocumentService.php`, `app/Http/Requests/Api/Student/StoreDocumentRequest.php`.
- Creado: `tests/Feature/RequirementCompletionTest.php`.
- Modificados: `tests/Feature/Admin/CoordinatorCareerTest.php`, `tests/Feature/EndToEndHomologacionTest.php`, `tests/Feature/PresentialManagementTest.php`, `tests/Feature/Student/DocumentWorkflowTest.php`, `tests/Feature/Student/NotificationResolutionTest.php`, `tests/Feature/Student/StudentWorkflowTestCase.php`.
- Actualizados: `docs/api.md`, `docs/guia-frontend.md`, `docs/openapi.json`.

Rutas relativas a `frontend-homologacion-ueb-main`:

- Modificados: `views/admin/usuarios.tsx`, `views/coordinator/estudiantes.tsx`, `views/password.tsx`, `views/student/perfil.tsx`, `INTEGRACION.md`.

En la raíz se creó este informe. El cambio previo del usuario en `backend-homologacion-main/.env.example` se conservó y no pertenece a esta implementación.

## Comandos para verificar los cambios

Desde la raíz en PowerShell, ejecutar en este orden:

```powershell
Set-Location backend-homologacion-main
php vendor/bin/pint --dirty --format agent
php artisan route:list --path=api --except-vendor --no-interaction
php artisan test --compact
php artisan scramble:export --path=docs/openapi.json --fail-on-unknown --no-interaction
Set-Location ..\frontend-homologacion-ueb-main
node node_modules/typescript/bin/tsc --noEmit
npm.cmd run build
Set-Location ..
git diff --check
```

La suite usa la base exclusiva configurada en `phpunit.xml` y ejecuta sus migraciones de prueba como parte de la preparación; no apunta a la base de desarrollo. Para crear la nueva clase de pruebas se ejecutó `php artisan make:test RequirementCompletionTest --phpunit --no-interaction`.

## Recorrido manual para ejecutar

Usar las cuentas disponibles y arrancar el backend y frontend como indica el README existente.

1. Administrador: crear facultad, modalidad y carrera; editar sus nombres; comprobar duplicados 422. Eliminar un catálogo utilizado debe dar 409. En Usuarios, eliminar una cuenta de prueba sin antecedentes ni solicitudes: confirmar, verificar que desaparezca y que su token deje de servir. Cancelar debe conservarla. Una cuenta con historial debe responder 409. La propia cuenta no muestra Eliminar y el backend también bloquea la operación.
2. Coordinador: crear estudiante con cédula de 10 dígitos o pasaporte válido, celular de 10 dígitos, correo único, origen existente, institución/tipo/período y destino asignado con modalidad habilitada. Origen y destino iguales no se admiten. Editar y comprobar que el último antecedente y la asignación se actualizan. Inyectar otro rol debe devolver 422; gestionar estudiantes ajenos debe fallar. Desactivar conserva el historial y se bloquea con solicitudes activas.
3. Estudiante: ingresar con la contraseña temporal, cambiarla por una de al menos 8 caracteres con letras y números y volver a iniciar sesión. Registrar cada tipo de institución con período `2025` o `2024-2025`; comprobar rechazo de `2024/2025`. Consultar el checklist presencial sin controles de subida. Un archivo histórico propio debe seguir descargándose; archivos de otro estudiante no deben estar accesibles.
4. Recuperación, repetir por rol: abrir Recuperar contraseña, solicitar enlace y comprobar la misma respuesta para un correo inexistente. En desarrollo con `MAIL_MAILER=log`, consultar el enlace en `storage/logs/laravel.log`; abre el frontend definido por el primer origen de `FRONTEND_URL`. Restablecer, iniciar sesión con la nueva clave y comprobar que la anterior y el enlace usado dejan de servir. Un token caducado o una clave sin letras/números deben rechazarse.

Para envío real usar el transporte SMTP existente y configurar `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` y `MAIL_FROM_ADDRESS` con los valores del proveedor, por ejemplo Mailtrap. Para desarrollo local puede mantenerse `MAIL_MAILER=log`. Tras editar la configuración ejecutar `php artisan config:clear`.

## Pendiente por alcance y sugerencias no implementadas

- Varias facultades por carrera, ciclos, columnas con los nombres solicitados, catálogo/seeding nuevo y retirada de `hr_carga_horaria` necesitan trabajo de base de datos, omitido por indicación del usuario.
- Sin FK para origen, su nombre funciona como dato del antecedente: renombrar una carrera del catálogo no actualiza los antecedentes históricos y puede requerir volver a seleccionar el origen al editar.
- La cuenta de coordinador conserva la eliminación como desactivación y el historial permanece intacto.
- El archivo `.env.example` contiene dos declaraciones de `FRONTEND_URL`; revisar cuál debe mantenerse. No se alteró porque tenía un cambio previo del usuario.
