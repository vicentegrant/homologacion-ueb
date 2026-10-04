<!DOCTYPE html>
<html lang="es">
<body style="font-family:Arial,sans-serif;color:#18352c;background:#f5f7f6;padding:24px">
<main style="max-width:560px;margin:auto;background:white;padding:32px;border-radius:12px">
    <h1>Bienvenido a Homologación UEB</h1>
    <p>Hola, {{ $nombre }}:</p>
    <p>Estas son tus credenciales de acceso:</p>
    <p><strong>Usuario:</strong> {{ $email }}<br><strong>Contraseña temporal:</strong> {{ $password }}</p>
    <p><a href="{{ $url }}" style="display:inline-block;background:#136847;color:white;padding:12px 20px;border-radius:6px">Iniciar sesión</a></p>
    <p>Debes cambiar tu contraseña en el primer ingreso.</p>
    @if ($expira)
        <p>La contraseña temporal caduca el {{ $expira }}. Si caduca, utiliza «Olvidé mi contraseña».</p>
    @endif
    <p>No compartas este correo.</p>
    <p>Dirección de acceso: <a href="{{ $url }}">{{ $url }}</a></p>
    <p>Sistema de homologación — UEB</p>
</main>
</body>
</html>
