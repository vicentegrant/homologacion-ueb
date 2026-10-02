# Conexión con el backend de homologación

1. Backend (carpeta `backend-homologacion`):
   - En `.env`: `FRONTEND_URL=http://localhost:3000,http://127.0.0.1:3000`
   - `php artisan config:clear` y `php artisan serve` (queda en http://127.0.0.1:8000)
   - Cuentas: `php artisan db:seed --class=AdminUserSeeder` (requiere `INITIAL_ADMIN_*`)
     y, para pruebas, `php artisan db:seed --class=StudentDemoSeeder` (test@example.com / password).
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
| Administrador | `#/usuarios`, `#/solicitudes`, `#/solicitudes/:id`, `#/estudiantes` |

Estructura: `components/app` (shell, UI común), `views/<rol>` (pantallas), `lib/api.ts` (cliente), `lib/router.ts` (rutas).
