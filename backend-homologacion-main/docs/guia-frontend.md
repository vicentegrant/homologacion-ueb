# Guía de integración para Frontend

Esta guía permite comenzar a consumir la API de homologación. El inventario completo de rutas, campos y validaciones está en [Contrato de la API](api.md). Backend y Frontend deben usar los documentos de la misma revisión de `main`.

También está disponible [OpenAPI en JSON](openapi.json), generado con Scramble y sin tipos desconocidos. Puede importarse en Postman u otra herramienta compatible. El servidor incluido es un ejemplo local: reemplazarlo por el backend acordado. La autenticación Bearer se configura con el token recibido al iniciar sesión. Para regenerarlo después de cambiar endpoints: `php artisan scramble:export --path=docs/openapi.json --fail-on-unknown`.

## Qué integrar primero

| Orden | Funcionalidad | Estado para integración |
| --- | --- | --- |
| 1 | Login, sesión, logout y pantallas por rol | Implementado; comenzar aquí. |
| 2 | Perfil del estudiante, antecedentes y catálogos | Implementado. El estudiante solo edita su celular. |
| 3 | Crear/listar solicitudes, cargar PDF y enviar a revisión | Implementado; requiere cuentas, asignaciones y requisitos de prueba preparados por Backend. |
| 4 | Consultas del coordinador, mallas, asignaturas y comparaciones | Implementado; probar con carreras autorizadas. |
| 5 | Ciclo de observaciones/correcciones e informe final | Corregido y cubierto por pruebas de regresión; disponible para integración. |

La suite automatizada comprueba el recorrido Administrador → Estudiante → Coordinador → Estudiante sobre PostgreSQL, además de autorización, archivos y casos de error. La integración visual y la aceptación conjunta con Frontend se realizan sobre la interfaz que construya ese equipo.

### Comportamiento acordado y correcciones incluidas

- Se pueden revisar varios documentos durante `en_revision` y `observado`. Solo el primer paso a `observado` agrega esa transición al historial; cada observación documental conserva su propio registro.
- Observar o reemplazar un archivo elimina sus verificaciones vigentes. El nuevo archivo requiere aprobación y una nueva verificación. Mientras la solicitud esté `observado`, el estudiante debe reenviarla antes de que avance a `en_proceso`.
- El informe técnico distribuye textos largos en varias páginas y conserva caracteres españoles. Una vez generado, se devuelve el mismo archivo en peticiones repetidas; no se sobrescribe después de remitirlo al Consejo.
- Administrador y Coordinador registran la resolución únicamente en `en_consejo`; la operación finaliza la solicitud en `listo`, habilitando su descarga por el estudiante.
- El registro público está deshabilitado: `POST /register` responde 403 y no crea cuentas ni tokens. El Administrador crea las cuentas mediante `POST /admin/users`; no construir autorregistro.
- `GET /roles` (Administrador) devuelve `roles` con nombres y `data` con objetos `{ id, nombre }`. Utilizar `data` para obtener el `rol_id` al crear usuarios.

El porcentaje de equivalencia y los créditos reconocidos los registra el Coordinador; no existe un cálculo automático basado en una normativa institucional. Los catálogos reales, el servidor de integración y las cuentas se preparan con Backend antes de una sesión conjunta.

## Conexión y cuentas

El frontend consume HTTP/JSON, nunca accede directamente a PostgreSQL. Backend debe entregar al equipo:

- Dirección accesible de la API y versión/commit que está ejecutándose.
- Cuentas de prueba por rol, entregadas por un canal privado.
- Un estudiante con carrera/coordinador asignados y requisitos documentales configurados.
- Confirmación del origen permitido por CORS y aviso de cambios de contrato.

Cada integrante puede levantar el backend en su computadora o utilizar un servidor de integración compartido. `localhost` apunta a la computadora donde se abre el navegador: no permite acceder automáticamente al equipo de otro integrante. Una copia en GitHub no equivale a una API desplegada.

Si se ejecuta el backend localmente, seguir los requisitos del [README](../README.md), configurar una base PostgreSQL exclusiva y ejecutar `composer install`, `php artisan migrate --seed` y `php artisan serve`. En una instalación nueva también se copia `.env.example` a `.env` y se genera `APP_KEY`. Al actualizar una instalación existente se conservan `.env` y `APP_KEY`; no usar `migrate:fresh`, pues elimina los datos.

Para una demostración local, Backend puede ejecutar `php artisan db:seed --class=StudentDemoSeeder`. El estudiante de prueba es `test@example.com` con contraseña inicial `password` (solo local/testing; el seeder no reemplaza contraseñas existentes). El coordinador de demostración tiene una contraseña aleatoria: un Administrador debe establecerle una mediante `PATCH /admin/users/{id}` antes de probar el recorrido de Coordinador. No hay una contraseña administrativa predeterminada. Para crear al Administrador inicial, Backend configura `INITIAL_ADMIN_*` en su `.env` y ejecuta `php artisan db:seed --class=AdminUserSeeder`.

Las carreras institucionales, requisitos y asignaciones de estudiantes se preparan actualmente con Backend; esta entrega no expone su mantenimiento completo por API. Los datos `[DEMO]` permiten integrar el recorrido sin inventar esos valores ni confundirlos con información institucional real.

Para React con Vite, configurar el `.env.local` del frontend con la dirección acordada. Ejemplo para un backend local en el puerto 8000:

```dotenv
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Esta variable es pública. Nunca incluir contraseñas de PostgreSQL, credenciales administrativas ni `APP_KEY` en variables del frontend.

Backend debe poner el origen exacto de la interfaz en `FRONTEND_URL`, por ejemplo `http://localhost:5173`, y ejecutar `php artisan config:clear` tras cambiarlo. `localhost` y `127.0.0.1` son orígenes diferentes; también importa el puerto. Se admiten varios orígenes separados por comas. Reiniciar Vite después de modificar su entorno.

## Login y peticiones JSON

Todas las rutas de las tablas siguientes son relativas a `/api/v1`. La autenticación actual utiliza Bearer, sin cookies de sesión ni `credentials: 'include'`.

```js
const API = import.meta.env.VITE_API_BASE_URL.replace(/\/$/, '');
let token = null;

async function api(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  const response = await fetch(`${API}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok) {
    throw Object.assign(new Error(data.message ?? 'La operación falló'), {
      status: response.status,
      errors: data.errors ?? {},
    });
  }
  return data;
}

async function login(email, password) {
  const session = await api('/login', {
    method: 'POST', body: { email, password },
  });
  token = session.token;
  return session.user;
}

// Después de login:
// const session = await api('/me');
// const profile = await api('/student/profile');
// await api('/logout', { method: 'POST' });
// token = null;
```

El ejemplo conserva el token en memoria: recargar la página requiere iniciar sesión otra vez. No registrar tokens en consola ni compartirlos en capturas. Un 401 en una petición protegida debe llevar a recuperar la sesión.

`/login` devuelve `{ success, user, token, token_type }`; `/me` devuelve `{ success, user }`. `user.roles` es un arreglo de nombres en minúsculas: `administrador`, `coordinador`, `estudiante`. Los endpoints de perfil/detalle normalmente usan `{ success, data }`. No asumir que todas las respuestas tienen la misma envoltura. La autorización definitiva siempre corresponde al backend.

## Primer recorrido del estudiante

| Paso | Petición | Entrada/uso |
| --- | --- | --- |
| Consultar perfil | `GET /student/profile` | Datos propios. |
| Editar celular | `PATCH /student/profile` | `{ "numero_celular": "0991234567" }`; demás datos en solo lectura. |
| Registrar antecedentes | `POST /student/antecedentes` | `universidad_origen`, `carrera_origen`, `tipo_institucion`, `periodo_cursado`. |
| Consultar opciones | `GET /student/catalogo` | Usar IDs reales de `data.asignaciones` y `data.tramites`. |
| Crear solicitud | `POST /student/solicitudes` | `coordinador_carrera_id`, `tramite_proceso_id`, `procedencia_estudios`. |
| Consultar detalle | `GET /student/solicitudes/{id}` | Requisitos, IDs de documentos, estado y permisos de edición. |
| Subir documentos | `POST /student/solicitudes/{id}/documentos/{documento}` | Un PDF en el campo `archivo`. |
| Enviar | `POST /student/solicitudes/{id}/enviar` | Sin body; requiere antecedentes y todos los archivos. |
| Seguimiento | `GET /student/solicitudes` | Lista propia, con paginación. |
| Avisos | `GET /student/notificaciones` | Lista propia; `PATCH /student/notificaciones/{uuid}/leer` marca lectura. |

La creación de una solicitud genera los requisitos; el frontend no inventa IDs ni crea documentos requeridos. Un catálogo sin asignaciones debe comunicarse a Backend para preparar la cuenta. No asumir que los IDs coinciden entre bases locales.

El detalle del estudiante entrega `estado_actual` como texto y banderas `puede_editar` y `puede_enviar`. Esta última indica una etapa que permite el envío, pero no garantiza que ya estén completos los requisitos. En el detalle administrativo/coordinador el estado tiene otra estructura: consultar [el contrato](api.md). Después de cada escritura, refrescar el detalle porque el estado puede cambiar automáticamente.

## Subir y descargar PDF

Para subir, usar `FormData` y dejar que el navegador genere `Content-Type` con su boundary:

```js
async function uploadDocument(solicitudId, documentoId, file) {
  const form = new FormData();
  form.append('archivo', file);
  const response = await fetch(
    `${API}/student/solicitudes/${solicitudId}/documentos/${documentoId}`,
    {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      body: form,
    },
  );
  const data = await response.json();
  if (!response.ok) {
    throw Object.assign(new Error(data.message ?? 'No se pudo subir el PDF'), {
      status: response.status, errors: data.errors ?? {},
    });
  }
  return data;
}
```

El límite predeterminado es 10 MB; Backend puede configurarlo y debe ajustar también los límites de carga de PHP. El archivo se valida por su contenido, no solo por la extensión.

Las descargas requieren el mismo Bearer. Un enlace HTML normal no añade esa cabecera. Solicitar el archivo con `fetch`, comprobar `response.ok`, leer `response.blob()` y descargarlo mediante una URL temporal creada con `URL.createObjectURL`; liberarla después con `URL.revokeObjectURL`. No interpretar una respuesta de error como PDF.

Los `download_url` relativos empiezan en `/api/v1/...`: resolverlos contra el origen del backend (`new URL(downloadUrl, new URL(API).origin)`), sin duplicar `/api/v1`. Usar únicamente destinos del backend acordado para enviar el token. La resolución del estudiante solo está disponible cuando la solicitud está `listo`.

## Errores, paginación y coordinación

| Código | Acción en la interfaz |
| --- | --- |
| 401 | Credenciales incorrectas en login o sesión ausente/inválida en rutas protegidas. |
| 403 | Informar falta de permiso o cuenta inactiva. |
| 404 | Informar recurso no disponible; también se usa para recursos ajenos. |
| 409 | Mostrar el mensaje y refrescar el detalle antes de reintentar. |
| 422 | Mostrar `errors` junto a los campos cuando exista; en otro caso mostrar `message`. |
| 429 | Informar límite de peticiones y esperar antes de reintentar. |
| 500 | Mostrar error general y comunicar la petición a Backend. |

Los listados paginados generalmente devuelven `data`, `links` y `meta`; los reportes tienen su bloque `paginacion`. Seguir el contrato de cada endpoint.

Para reportar un problema, enviar método, ruta, estado HTTP, body sin secretos, respuesta, rol utilizado, pasos de reproducción y commit del backend. No incluir contraseñas ni tokens. Acordar una primera prueba conjunta: login → perfil → catálogo → creación → carga de PDF → envío → consulta del coordinador. Mantener este documento y el contrato actualizados en el mismo repositorio.
