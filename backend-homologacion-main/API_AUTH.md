# Autenticación y autorización

La referencia vigente está en [docs/api.md](docs/api.md), incluyendo contraseña temporal, cambio obligatorio y recuperación. La API usa Laravel Sanctum con tokens Bearer, roles propios (`administrador`, `coordinador`, `estudiante`), comprobación de cuenta activa y bloqueo hasta cambiar la contraseña temporal.
