# Convenciones de desarrollo

Estas convenciones guían el desarrollo del backend. El estado actual se detalla en [arquitectura](arquitectura-backend.md).

## Idioma y nombres

El idioma del dominio es español. Se conservan términos técnicos y sufijos del ecosistema Laravel, así como el endpoint técnico `health` ya aprobado.

| Elemento | Convención |
|---|---|
| Clases PHP | PascalCase. |
| Métodos y variables | camelCase. |
| Tablas y columnas | snake_case; nombres concretos pendientes de revisión de la base. |
| Endpoints funcionales | Plural, minúsculas y kebab-case cuando sea necesario. |
| Controllers | Sufijo `Controller`. |
| Form Requests | `Store...Request` y `Update...Request` para creación y actualización. |
| API Resources | Sufijo `Resource`. |
| Services | Sufijo `Service`. |
| Policies | Sufijo `Policy`. |

## Responsabilidades

Usar Form Requests para validar entradas de operaciones funcionales y API Resources para representar sus respuestas. Mantener Controllers delgados. Usar Services solamente cuando exista lógica de negocio que centralizar; no crear uno por cada tabla. Models representan entidades y relaciones, y Policies concentran autorización.

La ruta de salud usa una función y una respuesta JSON directa como excepción aceptada por su simplicidad. No necesita validación de entrada, Service ni Resource propio. El contrato vigente está en [API](api.md).

## Pruebas

- **Feature:** en `tests/Feature`, extender `Tests\TestCase` y verificar el comportamiento HTTP observable: estados, contratos JSON y, cuando existan, validaciones y permisos. Usar `assertExactJson` cuando el contrato exija igualdad completa, como en salud.
- **Unit:** en `tests/Unit`, comprobar lógica aislada sin depender de HTTP ni de la base de datos; extender `PHPUnit\Framework\TestCase` cuando no se necesite la aplicación.
- Nombrar clases con sufijo `Test` y métodos descriptivos `test_...`, siguiendo el estilo existente. Es una excepción a camelCase para identificar métodos de prueba con PHPUnit.
- Usar migraciones incrementales para cambios nuevos y no reescribir migraciones ya aplicadas.

Verificaciones mínimas antes de integrar cambios:

```sh
php artisan test
vendor/bin/pint --test
git diff --check
```

## Git y seguridad

Usar Conventional Commits: `tipo(alcance): descripción`, con alcance opcional.

| Tipo | Uso |
|---|---|
| feat | Nueva funcionalidad. |
| fix | Corrección de errores. |
| docs | Documentación. |
| test | Pruebas. |
| refactor | Reorganización sin cambiar comportamiento. |
| chore | Mantenimiento técnico. |

No subir `.env`, claves ni credenciales. `.env.example` debe contener solo configuración de ejemplo sin secretos. Revisar el contenido preparado con `git diff --cached` antes de confirmar cambios.

Flujo de ramas acordado:

```text
main → develop → feature/*
```

Las ramas `feature/*` nacen desde `develop`. Los Pull Requests de funcionalidades se dirigen hacia `develop`. La integración de `develop` en `main` se realiza únicamente después de revisión. No subir directamente a `main`. Este flujo describe la convención de trabajo; no acredita que existan protecciones automáticas configuradas en el remoto, pues no fueron verificadas.
