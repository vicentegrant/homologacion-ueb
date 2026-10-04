<?php

use Database\Seeders\DevelopmentAuthSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Mailtrap\EmailHeader\CategoryHeader;
use Mailtrap\Exception\HttpException;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:reset-demo', function (): int {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Este comando solo se permite en local/testing.');

        return Command::FAILURE;
    }
    // El mismo seeder permite crear las cuentas y repetir el flujo sin borrar la base.
    $this->call('db:seed', ['--class' => DevelopmentAuthSeeder::class, '--no-interaction' => true]);

    return Command::SUCCESS;
})->purpose('Crear o reiniciar únicamente las tres cuentas de autenticación de desarrollo');

Artisan::command('send-mail {--to= : Recipient email; defaults to MAIL_TEST_TO} {--mailer= : Mailer to test; defaults to MAIL_MAILER}', function (): int {
    $mailer = $this->option('mailer') ?? config('mail.default');
    $transport = config('mail.mailers.'.$mailer.'.transport');
    if (! in_array($transport, ['smtp', 'mailtrap-sdk'], true)) {
        $this->error('Selecciona un mailer SMTP o Mailtrap para comprobar un envío real.');

        return Command::FAILURE;
    }
    $apiKey = config('services.mailtrap-sdk.apiKey');
    if ($transport === 'mailtrap-sdk' && (! is_string($apiKey) || trim($apiKey) === '' || str_contains($apiKey, '<YOUR_API_TOKEN>'))) {
        $this->error('Configure MAILTRAP_API_KEY in .env with your Email Sending API token.');

        return Command::FAILURE;
    }

    $recipient = $this->option('to') ?? config('mail.test_to') ?? ($transport === 'mailtrap-sdk' ? config('services.mailtrap-sdk.test_to') : null);
    $validator = Validator::make([
        'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        'recipient' => $recipient,
    ], [
        'MAIL_FROM_ADDRESS' => ['required', 'email:rfc'],
        'recipient' => ['required', 'email:rfc'],
    ]);
    if ($validator->fails()) {
        $this->error('Configura MAIL_FROM_ADDRESS y un destinatario válido mediante --to o MAIL_TEST_TO.');

        return Command::FAILURE;
    }

    try {
        $sent = Mail::mailer($mailer)->raw('La integración de correo de Homologación UEB está funcionando.', function (Message $message) use ($recipient, $transport): void {
            $message->to($recipient)->subject('Prueba de correo - Homologación UEB');
            if ($transport === 'mailtrap-sdk') {
                $message->getSymfonyMessage()->getHeaders()->add(new CategoryHeader('Integration Test'));
            }
        });
        if ($sent === null) {
            $this->error('The message was not sent. Check your mail event listeners.');

            return Command::FAILURE;
        }
    } catch (Throwable $exception) {
        $this->error($transport === 'mailtrap-sdk'
            ? 'Mailtrap sending failed. Check the API token, verified sender domain and recipient permissions.'
            : 'Falló el envío SMTP. Revisa el servidor, puerto, usuario y contraseña de aplicación.');
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof HttpException) {
                $this->error(match ($cause->getCode()) {
                    401 => 'HTTP 401: Mailtrap rechazó el token. Usa un token de Email Sending válido.',
                    403 => 'HTTP 403: Revisa los permisos del token, el dominio verificado y las restricciones del destinatario.',
                    429 => 'HTTP 429: Se alcanzó el límite de envío de Mailtrap. Espera antes de reintentar.',
                    default => 'Mailtrap respondió con HTTP '.(int) $cause->getCode().'. Revisa la configuración y los registros de envío.',
                });
                break;
            }
            $reason = strtolower($cause->getMessage());
            if ($transport === 'smtp' && str_contains($reason, 'authenticate')) {
                $this->error('El servidor rechazó la autenticación SMTP. En Gmail utiliza una contraseña de aplicación, no la contraseña habitual.');
                break;
            }
            if (str_contains($reason, 'certificate') || str_contains($reason, 'ssl')) {
                $this->error($transport === 'mailtrap-sdk'
                    ? 'La conexión HTTPS falló al validar el certificado. Revisa curl.cainfo y openssl.cafile en el php.ini de consola.'
                    : 'Falló la validación del certificado SMTP. Revisa openssl.cafile en el php.ini de consola.');
                break;
            }
            if (str_contains($reason, 'resolve host') || str_contains($reason, 'getaddrinfo')) {
                $this->error('No se pudo resolver el servidor de correo. Revisa el host configurado y la conexión DNS.');
                break;
            }
            if (str_contains($reason, 'failed to connect') || str_contains($reason, 'timed out')) {
                $this->error('No se pudo conectar con el servidor de correo. Revisa la conexión, el proxy y el firewall.');
                break;
            }
            if ($cause->getPrevious() === null) {
                $this->error('Tipo de error: '.$cause::class.' (código '.(int) $cause->getCode().').');
            }
        }

        return Command::FAILURE;
    }

    $this->info($transport === 'mailtrap-sdk'
        ? 'Mailtrap accepted the message. Check https://mailtrap.io/sending/email_logs'
        : 'El servidor SMTP aceptó el correo. Revisa la bandeja de entrada y spam del destinatario.');

    return Command::SUCCESS;
})->purpose('Enviar un correo de prueba mediante el transporte configurado');
