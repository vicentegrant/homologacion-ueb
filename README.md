# Sistema de Reconocimiento y Homologación — UEB

Sistema web para gestionar solicitudes de reconocimiento y homologación de estudios en la Universidad Estatal de Bolívar.

| Carpeta | Tecnología | Función |
| --- | --- | --- |
| `backend-homologacion-main` | Laravel 13 + PostgreSQL | API REST: autenticación, reglas de negocio, archivos y reportes |
| `frontend-homologacion-ueb-main` | Next.js 16 + React | Interfaz web para Administrador, Coordinador y Estudiante |

El frontend se comunica con el backend por HTTP (`/api/v1`) usando tokens Bearer.

---

## 1. Requisitos

Instalar antes de empezar:

- **PHP 8.3 o superior** con las extensiones `pdo_pgsql` y `pgsql` activadas en `php.ini`
- **Composer**
- **PostgreSQL** (y conocer la contraseña del usuario `postgres`)
- **Node.js 20 o superior** (incluye `npm`)
- **Git**

Comprobar en una terminal:

```powershell
php -v
composer -V
node -v
npm -v
```

---

## 2. Clonar el repositorio

```powershell
git clone https://github.com/vicentegrant/homologacion-ueb.git
cd homologacion-ueb
```

---

## 3. Configurar el backend

### 3.1 Crear la base de datos

En pgAdmin: clic derecho en **Databases → Create → Database**, nombre `backend_homologacion`.

O desde la terminal, si `psql` está disponible:

```powershell
psql -U postgres -c "CREATE DATABASE backend_homologacion;"
```

### 3.2 Instalar dependencias y crear el `.env`

```powershell
cd backend-homologacion-main
composer install
Copy-Item .env.example .env
php artisan key:generate
```

### 3.3 Editar el `.env`

Abrir `backend-homologacion-main/.env` y completar:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=backend_homologacion
DB_USERNAME=postgres
DB_PASSWORD=contraseña_de_postgres

FRONTEND_URL=http://localhost:3000,http://127.0.0.1:3000

INITIAL_ADMIN_NAME="Administrador inicial"
INITIAL_ADMIN_EMAIL=admin@ueb.edu.ec
INITIAL_ADMIN_PASSWORD=UnaClaveSegura123!
INITIAL_ADMIN_CEDULA=0200000000
INITIAL_ADMIN_PHONE=0990000000
```

> El `.env` contiene contraseñas: **nunca** se sube a GitHub.

### 3.4 Crear tablas y datos iniciales

```powershell
php artisan config:clear
php artisan migrate --seed
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=StudentDemoSeeder
```

`StudentDemoSeeder` crea datos de prueba (estudiante, coordinador y una carrera `[DEMO]`). Solo funciona en entorno local.

---

## 4. Configurar el frontend

```powershell
cd ..\frontend-homologacion-ueb-main
npm install
Copy-Item .env.example .env.local
```

`.env.local` debe contener la dirección de la API:

```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1
```

---

## 5. Poner en funcionamiento

Se necesitan **dos terminales abiertas** al mismo tiempo.

**Terminal 1 — backend**

```powershell
cd backend-homologacion-main
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 2 — frontend**

```powershell
cd frontend-homologacion-ueb-main
npm run dev
```

Abrir **http://localhost:3000** en el navegador.

Para comprobar que la API responde: http://127.0.0.1:8000/api/v1/health

Para apagar, presionar `Ctrl + C` en cada terminal. Las siguientes veces solo se repite este paso 5.

---

## 6. Cuentas de prueba

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Administrador | El definido en `INITIAL_ADMIN_EMAIL` | El definido en `INITIAL_ADMIN_PASSWORD` |
| Estudiante | `test@example.com` | `password` |
| Coordinador | `coordinador-demo@example.com` | Asignarla primero (ver abajo) |

El coordinador de demostración se crea con una contraseña aleatoria. Para usarlo, iniciar sesión como Administrador, ir a **Usuarios**, editar a "Coordinador de demostración" y escribir una nueva contraseña.

> Estas cuentas son solo para desarrollo. No usar en producción.

---

## 7. Flujo de prueba sugerido

1. **Estudiante:** en *Mi perfil* registrar un antecedente académico; en *Mis solicitudes* crear una solicitud, subir los PDF requeridos y enviarla a revisión.
2. **Coordinador:** en *Solicitudes* abrir la solicitud, aprobar y verificar cada documento, y avanzarla a *Análisis académico*.
3. **Coordinador:** en *Mallas curriculares* crear la malla institucional de la carrera y la malla de origen del estudiante, con sus asignaturas; luego registrar comparaciones, el resultado y el informe técnico.
4. **Coordinador o Administrador:** avanzar a *En Consejo* y registrar la resolución (PDF).
5. **Estudiante:** descargar la resolución final.

---

## 8. Problemas comunes

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| `Could not open input file: artisan` | La terminal no está en la carpeta del backend | `cd backend-homologacion-main` |
| `vendor/autoload.php: Failed to open stream` | Faltan dependencias | `composer install` |
| `could not find driver` | Extensión de PostgreSQL desactivada | Activar `extension=pdo_pgsql` y `extension=pgsql` en `php.ini` |
| `fe_sendauth: no password supplied` | `DB_PASSWORD` vacío | Completar el `.env` y ejecutar `php artisan config:clear` |
| *No se pudo conectar con la API* en el login | Backend apagado o CORS | Verificar la terminal del backend y que `FRONTEND_URL` incluya la dirección exacta del navegador; luego `php artisan config:clear` y reiniciar `serve` |
| `ERR_CONNECTION_REFUSED` en `127.0.0.1:8000` | El backend escucha en otra dirección | Iniciar con `php artisan serve --host=127.0.0.1 --port=8000` |
| *Credenciales incorrectas* con el administrador | El administrador no se creó | Revisar `INITIAL_ADMIN_*` en el `.env` y repetir `php artisan db:seed --class=AdminUserSeeder` |
| `&&` no es un separador válido | PowerShell de Windows | Ejecutar los comandos en líneas separadas o unirlos con `;` |

---

## 9. Documentación adicional

- Contrato de la API: `backend-homologacion-main/docs/api.md`
- Guía para el frontend: `backend-homologacion-main/docs/guia-frontend.md`
- Base de datos: `backend-homologacion-main/docs/base-de-datos.md`
- Integración y rutas del frontend: `frontend-homologacion-ueb-main/INTEGRACION.md`

---

## 10. Subir cambios

```powershell
git add .
git commit -m "Descripción del cambio"
git push
```

Nunca se suben: `.env`, `.env.local`, `vendor/`, `node_modules/` ni `.next/` (ya están excluidos en los `.gitignore`).
