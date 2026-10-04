<!DOCTYPE html>
<html lang="es">
<body style="font-family:Arial,sans-serif;color:#18352c;background:#f5f7f6;padding:24px">
<main style="max-width:560px;margin:auto;background:white;padding:32px;border-radius:12px">
    <h1>Restablecer contraseña</h1>
    <p>Hola, {{ $nombre }}:</p>
    <p>Recibimos una solicitud para restablecer tu contraseña del sistema de homologación UEB.</p>
    <p><a href="{{ $url }}" style="display:inline-block;background:#136847;color:white;padding:12px 20px;border-radius:6px">Crear nueva contraseña</a></p>
    <p>El enlace caduca en {{ $minutos }} minutos y se puede utilizar una sola vez.</p>
    <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
    <p>Si el botón no funciona, copia este enlace en tu navegador: <a href="{{ $url }}">{{ $url }}</a></p>
    <p>Sistema de homologación — UEB</p>
</main>
</body>
</html>
