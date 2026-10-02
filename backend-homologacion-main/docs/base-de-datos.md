# Base de datos

PostgreSQL es el motor definitivo. `.env.example` contiene la plantilla de conexión y `phpunit.xml` fuerza una base exclusiva `backend_homologacion_test`.

## Preparación local

```sh
createdb backend_homologacion
createdb backend_homologacion_test
php artisan migrate --seed
php artisan migrate:status
```

Nunca ejecutes `migrate:fresh` sobre una base compartida o productiva. Las credenciales reales solo pertenecen a `.env` o al gestor de secretos; no deben versionarse.

Los comandos `createdb` requieren acceso administrativo y deben usar el host/usuario apropiados para cada equipo. Ambas bases deben pertenecer al usuario de conexión configurado. El servidor local puede ser PostgreSQL 17 (verificado); no es necesario instalar PostgreSQL 18 para esta entrega. Las pruebas heredan host, puerto y credenciales del entorno, pero fuerzan la base `backend_homologacion_test`. Ejecutar `php artisan config:clear` antes de pruebas si existía configuración cacheada.

## Roles

La fuente de verdad es:

```text
roles(id, nombre UNIQUE, timestamps)
user_has_rol(user_id, rol_id, timestamps, PRIMARY KEY(user_id, rol_id))
```

Los valores sembrados son `administrador`, `coordinador` y `estudiante`. La migración correctiva copia asignaciones históricas desde `model_has_roles`, normaliza nombres y elimina esa tabla para impedir dos fuentes de verdad.

## Integridad y concurrencia

Las claves foráneas protegen propietarios y relaciones. Existen restricciones únicas para correo, cédula, roles, asignaciones de rol, Coordinador–Carrera, Estudiante–Coordinación, requisitos por solicitud, comparaciones, resultado, resolución y número de resolución. Los servicios bloquean la solicitud o documento antes de decisiones críticas; la base actúa como última barrera ante carreras concurrentes.

## Seeders

Los seeders idempotentes crean roles, tipos de trámite/proceso, estados documentales, estados de solicitud y combinaciones habilitadas. El administrador inicial solo se crea con todas las variables `INITIAL_ADMIN_*`. Los datos de demostración se limitan a entornos local/testing.

## Archivos y respaldo

La base guarda metadatos y rutas privadas; los binarios viven en `storage/app/private`. Un respaldo operativo debe incluir PostgreSQL y ese almacenamiento en un punto temporal coherente. Deben probarse restauraciones antes de producción.
