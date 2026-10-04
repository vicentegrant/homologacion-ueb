<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

class SmtpSendingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.default' => 'smtp',
            'mail.test_to' => 'recipient@example.org',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.scheme' => 'smtps',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.username' => 'sender@gmail.com',
            'mail.mailers.smtp.password' => 'test-app-password',
            'mail.from.address' => 'sender@gmail.com',
            'mail.from.name' => 'Homologación UEB',
            'services.mailtrap-sdk.apiKey' => null,
        ]);
    }

    private function captureSmtp(): ArrayTransport
    {
        $mailer = Mail::mailer('smtp');
        $this->assertInstanceOf(EsmtpTransport::class, $mailer->getSymfonyTransport());
        $this->assertSame('smtps://smtp.gmail.com', (string) $mailer->getSymfonyTransport());
        $capture = new ArrayTransport;
        $mailer->setSymfonyTransport($capture);

        return $capture;
    }

    public function test_command_uses_default_smtp_without_a_mailtrap_token(): void
    {
        $capture = $this->captureSmtp();
        $this->artisan('send-mail')->expectsOutput('El servidor SMTP aceptó el correo. Revisa la bandeja de entrada y spam del destinatario.')->assertSuccessful();

        $email = $capture->messages()->sole()->getOriginalMessage();
        $this->assertSame('sender@gmail.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('recipient@example.org', $email->getTo()[0]->getAddress());
        $this->assertNull($email->getHeaders()->get('category'));
    }

    public function test_smtp_can_be_selected_explicitly_with_a_recipient(): void
    {
        config(['mail.default' => 'mailtrap-sdk', 'mail.test_to' => null]);
        $capture = $this->captureSmtp();
        $this->artisan('send-mail', ['--mailer' => 'smtp', '--to' => 'override@example.org'])->assertSuccessful();
        $email = $capture->messages()->sole()->getOriginalMessage();
        $this->assertSame('override@example.org', $email->getTo()[0]->getAddress());
    }

    public function test_existing_notifications_use_default_smtp(): void
    {
        $capture = $this->captureSmtp();
        $user = new User(['nombres_completos' => 'Usuario de prueba', 'email' => 'recipient@example.org']);
        $user->notify(new TemporaryPasswordNotification('TemporaryPassword123'));
        $user->notify(new ResetPasswordNotification('reset-token'));

        $this->assertCount(2, $capture->messages());
        foreach ($capture->messages() as $message) {
            $email = $message->getOriginalMessage();
            $this->assertSame('sender@gmail.com', $email->getFrom()[0]->getAddress());
            $this->assertSame('recipient@example.org', $email->getTo()[0]->getAddress());
            $this->assertNotEmpty($email->getHtmlBody());
        }
    }

    public function test_authentication_failure_does_not_expose_smtp_credentials(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new TransportException('Failed to authenticate with secret-password'));
        Mail::mailer('smtp')->setSymfonyTransport($transport);

        $this->artisan('send-mail')
            ->expectsOutput('El servidor rechazó la autenticación SMTP. En Gmail utiliza una contraseña de aplicación, no la contraseña habitual.')
            ->doesntExpectOutputToContain('secret-password')
            ->assertFailed();
    }

    public function test_log_mailer_is_rejected_instead_of_reporting_a_real_delivery(): void
    {
        config(['mail.default' => 'log']);
        Mail::shouldReceive('mailer')->never();

        $this->artisan('send-mail')->expectsOutput('Selecciona un mailer SMTP o Mailtrap para comprobar un envío real.')->assertFailed();
    }
}
