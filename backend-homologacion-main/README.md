# Sistema de Reconocimiento y Homologación — Backend

Para el equipo de Frontend: [guía de integración](docs/guia-frontend.md) y [contrato completo de endpoints](docs/api.md). Incluyen autenticación, ejemplos, preparación de cuentas y comportamiento del flujo corregido.

Para importar las peticiones en herramientas compatibles: [contrato OpenAPI JSON](docs/openapi.json).

API REST desacoplada para el flujo institucional de reconocimiento y homologación:

```text
React → Laravel API → PostgreSQL
```

Laravel concentra autenticación, autorización, reglas de negocio, auditoría, persistencia, archivos privados, notificaciones y reportes. El frontend React consume JSON y tokens Bearer; no comparte lógica de negocio con el backend.

## Requisitos e instalación

- PHP 8.3 o superior con `pdo_pgsql` y `pgsql`.
- PostgreSQL (verificado localmente con 17.10).
- Composer.

```sh
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Configura una base exclusiva antes de migrar:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=backend_homologacion
DB_USERNAME=postgres
DB_PASSWORD=
FRONTEND_URL=http://localhost:5173
MAX_PRIVATE_PDF_SIZE_KB=10240
STUDENT_MAIL_NOTIFICATIONS=false
```

`FRONTEND_URL` puede contener orígenes separados por coma. CORS nunca usa `*` como origen. Los PDF se validan por MIME y tamaño configurable y se guardan en `storage/app/private`; solo se descargan mediante endpoints autorizados.

El administrador inicial se crea únicamente cuando `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_CEDULA`, `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PHONE` e `INITIAL_ADMIN_PASSWORD` están completos. No existen credenciales administrativas predeterminadas.

Para crear ese administrador después de configurar las variables, ejecutar `php artisan db:seed --class=AdminUserSeeder`. El seeder no cambia la contraseña de una cuenta ya existente. Para datos exclusivamente de prueba, `php artisan db:seed --class=StudentDemoSeeder` prepara un estudiante, carrera, asignación y requisitos; no sustituye catálogos institucionales. Ver [guía para Frontend](docs/guia-frontend.md).

Al actualizar: conservar `.env` y `APP_KEY`, ejecutar `composer install`, `php artisan migrate` y `php artisan config:clear`. `migrate:fresh` borra las tablas y no forma parte del procedimiento de actualización.

## Seguridad y roles

Sanctum autentica tokens Bearer. La fuente de verdad de roles es:

- `roles.nombre`, único y en minúsculas;
- `user_has_rol(user_id, rol_id)`, con clave compuesta;
- valores oficiales: `administrador`, `coordinador`, `estudiante`.

No se usa `model_has_roles` para asignaciones. Spatie permanece instalado como dependencia auxiliar histórica, pero no decide autorización ni persiste roles de usuario. Las rutas aplican `auth:sanctum`, cuenta activa y middleware de rol propio. Un recurso ajeno al Coordinador o Estudiante responde 404 para no revelar su existencia.

## Módulos

### Administrador

- Usuarios: alta, consulta, edición, activación/desactivación, rol y creador.
- Carreras por Coordinador y consulta de estudiantes.
- Consulta y filtros de solicitudes.
- Resoluciones privadas, reporte JSON y dashboard.

### Estudiante

- Perfil propio; solo puede modificar `numero_celular`.
- Antecedentes académicos.
- Solicitudes, documentos PDF, correcciones y seguimiento.
- Notificaciones internas y descarga de resolución al finalizar.

### Coordinador

- Alcance estricto por carreras asignadas.
- Revisión y verificación documental.
- Mallas, asignaturas, sílabos y comparaciones.
- Resultado `total`, `parcial` o `rechazada`, informe técnico y resolución.
- Historial de estados con actor, fecha, observación y etapa de origen.

La secuencia principal es:

```text
pendiente → en_revision → observado → en_revision → en_proceso
en_proceso → aprobado → en_consejo → listo
en_revision | observado | en_proceso | en_consejo → rechazado
```

Cada transición comprueba sus precondiciones. El rechazo exige motivo y registra la etapa desde la que ocurrió. `en_proceso → aprobado/rechazado` también exige un resultado académico coherente.

## API

La base es `/api/v1`. Inicio de sesión: `POST /login`; sesión: `GET /me` y `POST /logout`. El Administrador crea las cuentas; `POST /register` está deshabilitado y devuelve 403. Los módulos usan los prefijos `/admin`, `/student` y `/coordinator`.

Enviar siempre `Accept: application/json` y `Authorization: Bearer {token}` en rutas protegidas. Los códigos relevantes son 201, 401, 403, 404, 409 y 422. Los listados paginados incluyen `data`, `links` y `meta`. El contrato completo está en [docs/api.md](docs/api.md).

## Historias de usuario

| HU | Rol | Implementación | Estado |
| --- | --- | --- | --- |
| HU-01 | Administrador | Registrar usuarios con rol y trazabilidad | COMPLETADA |
| HU-02 | Administrador | Gestionar usuarios y estado de cuenta | COMPLETADA |
| HU-03 | Administrador | Control de acceso y cuentas activas | COMPLETADA |
| HU-04 | Estudiante | Consultar perfil y editar solo celular | COMPLETADA |
| HU-05 | Estudiante | Gestionar antecedentes académicos | COMPLETADA |
| HU-06 | Admin/Coordinador | Consultar estudiantes según alcance | COMPLETADA |
| HU-07 | Estudiante | Crear y consultar solicitudes propias | COMPLETADA |
| HU-08 | Estudiante | Cargar documentos privados | COMPLETADA |
| HU-09 | Coordinador | Revisar documentación | COMPLETADA |
| HU-10 | Estudiante | Corregir documentos observados | COMPLETADA |
| HU-11 | Coordinador | Verificar requisitos documentales | COMPLETADA |
| HU-12 | Coordinador | Gestionar asignaturas y sílabos | COMPLETADA |
| HU-13 | Coordinador | Gestionar mallas curriculares | COMPLETADA |
| HU-14 | Coordinador | Comparar asignaturas | COMPLETADA |
| HU-15 | Coordinador | Resultado e informe técnico | COMPLETADA |
| HU-16 | Coordinador | Estados e historial auditable | COMPLETADA |
| HU-17 | Admin/Coordinador | Registrar resolución externa | COMPLETADA |
| HU-18 | Estudiante | Consultar seguimiento e historial | COMPLETADA |
| HU-19 | Estudiante | Recibir notificaciones internas | COMPLETADA |
| HU-20 | Estudiante | Descargar resolución final | COMPLETADA |
| HU-21 | Admin/Coordinador | Filtrar y consultar solicitudes | COMPLETADA |
| HU-22 | Admin/Coordinador | Reportes JSON y agregados | COMPLETADA |

## Verificación

Las pruebas usan obligatoriamente `backend_homologacion_test` en PostgreSQL, separada de la base de desarrollo. Crear esa base con el usuario configurado como propietario antes de ejecutar la suite. Host, puerto, usuario y contraseña se leen de `.env` (o `.env.testing` si se utiliza); `phpunit.xml` fija el nombre de la base de pruebas y vacía `DB_URL` para evitar usar accidentalmente la base de trabajo. No contiene credenciales de una computadora particular.

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
php artisan route:list --path=api --except-vendor
php artisan scramble:analyze --fail-on-unknown
git diff --check
```

La suite cubre contratos HTTP, roles, aislamiento, archivos, transiciones válidas e inválidas, resultados total/parcial/rechazado, restricciones únicas de PostgreSQL, CORS y un flujo integral Administrador → Estudiante → Coordinador → Estudiante.

## Decisiones y deuda técnica

- PostgreSQL es el único motor soportado y probado; SQLite ya no forma parte de la configuración de pruebas.
- La migración correctiva conserva tablas de permisos auxiliares de Spatie, pero elimina `model_has_roles` y las columnas de rol incompatibles. `roles.nombre + user_has_rol` es la única fuente de verdad.
- `resoluciones_solicitud.coordinador_id` almacena al actor que registra la resolución incluso si es Administrador; un futuro cambio compatible podría renombrarlo a `registrado_por_id`.
- Los reportes se entregan en JSON. No se añadió exportación Excel porque no es necesaria para cerrar el flujo funcional.
- El correo es opcional y encolado (`STUDENT_MAIL_NOTIFICATIONS=false` por defecto), por lo que SMTP no bloquea la transacción principal.
- Antes de producción deben configurarse colas, respaldo/restauración, antivirus de archivos, secretos externos, HTTPS y observabilidad.
- El informe técnico admite varias páginas y caracteres españoles con la fuente estándar Courier/WinAnsi. No incorpora firma digital ni generación automática de la resolución externa: el PDF de resolución se carga desde el documento emitido por Consejo.
