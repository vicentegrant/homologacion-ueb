<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Support\Facades\Mail;
use Mailtrap\Api\EmailsSendApiInterface;
use Mailtrap\Bridge\Transport\MailtrapSdkTransport;
use Mailtrap\Config;
use Mailtrap\Exception\HttpClientException;
use Mockery;
use Nyholm\Psr7\Response;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailtrapSendingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.default' => 'mailtrap-sdk',
            'mail.test_to' => null,
            'services.mailtrap-sdk.apiKey' => 'test-api-key',
            'services.mailtrap-sdk.host' => 'send.api.mailtrap.io',
            'services.mailtrap-sdk.test_to' => 'recipient@example.org',
            'mail.from.address' => 'sender@example.org',
            'mail.from.name' => 'Homologación UEB',
        ]);
    }

    private function sendingApi(): EmailsSendApiInterface
    {
        $api = Mockery::mock(EmailsSendApiInterface::class);
        $mailer = Mail::mailer('mailtrap-sdk');
        $this->assertInstanceOf(MailtrapSdkTransport::class, $mailer->getSymfonyTransport());
        $this->assertSame('mailtrap+sdk://send.api.mailtrap.io', (string) $mailer->getSymfonyTransport());
        $mailer->setSymfonyTransport(new MailtrapSdkTransport($api, new Config('test-api-key')));

        return $api;
    }

    public function test_command_sends_with_configured_sender_recipient_and_category(): void
    {
        $this->sendingApi()->shouldReceive('send')->once()->with(Mockery::on(function (Email $email): bool {
            $this->assertSame('sender@example.org', $email->getFrom()[0]->getAddress());
            $this->assertSame('Homologación UEB', $email->getFrom()[0]->getName());
            $this->assertSame('recipient@example.org', $email->getTo()[0]->getAddress());
            $this->assertSame('Integration Test', $email->getHeaders()->get('category')->getBodyAsString());
            $this->assertStringContainsString('Homologación UEB', $email->getSubject());
            $this->assertStringContainsString('integración', $email->getTextBody());

            return true;
        }))->andReturn(new Response(200, ['Content-Type' => 'application/json'], '{"message_ids":["test-message"]}'));

        $this->artisan('send-mail')->expectsOutput('Mailtrap accepted the message. Check https://mailtrap.io/sending/email_logs')->assertSuccessful();
    }

    public function test_recipient_option_overrides_environment_configuration(): void
    {
        $this->sendingApi()->shouldReceive('send')->once()->with(Mockery::on(fn (Email $email): bool => $email->getTo()[0]->getAddress() === 'override@example.org'))->andReturn(new Response(200, ['Content-Type' => 'application/json'], '{"message_ids":["test-message"]}'));

        $this->artisan('send-mail', ['--to' => 'override@example.org'])->assertSuccessful();
    }

    public function test_missing_token_prevents_sending(): void
    {
        config(['services.mailtrap-sdk.apiKey' => '']);
        Mail::shouldReceive('mailer')->never();

        $this->artisan('send-mail')->expectsOutput('Configure MAILTRAP_API_KEY in .env with your Email Sending API token.')->assertFailed();
    }

    public function test_invalid_recipient_prevents_sending(): void
    {
        Mail::shouldReceive('mailer')->never();

        $this->artisan('send-mail', ['--to' => 'invalid'])->expectsOutput('Configura MAIL_FROM_ADDRESS y un destinatario válido mediante --to o MAIL_TEST_TO.')->assertFailed();
    }

    public function test_transport_failure_does_not_expose_the_api_response(): void
    {
        $this->sendingApi()->shouldReceive('send')->once()->andThrow(new RuntimeException('secret-token-and-response'));

        $this->artisan('send-mail')->expectsOutput('Mailtrap sending failed. Check the API token, verified sender domain and recipient permissions.')->doesntExpectOutputToContain('secret-token-and-response')->assertFailed();
    }

    public function test_existing_notifications_use_the_mailtrap_transport(): void
    {
        config(['mail.default' => 'mailtrap-sdk']);
        $this->sendingApi()->shouldReceive('send')->twice()->with(Mockery::on(function (Email $email): bool {
            $this->assertSame('recipient@example.org', $email->getTo()[0]->getAddress());
            $this->assertSame('sender@example.org', $email->getFrom()[0]->getAddress());
            $this->assertNotEmpty($email->getHtmlBody());

            return true;
        }))->andReturn(new Response(200, ['Content-Type' => 'application/json'], '{"message_ids":["notification-message"]}'));

        $user = new User(['nombres_completos' => 'Usuario de prueba', 'email' => 'recipient@example.org']);
        $user->notify(new TemporaryPasswordNotification('TemporaryPassword123'));
        $user->notify(new ResetPasswordNotification('reset-token'));
    }

    public function test_rejected_token_reports_status_without_exposing_provider_details(): void
    {
        $this->sendingApi()->shouldReceive('send')->once()->andThrow(new HttpClientException('secret-token-and-response', 401));

        $this->artisan('send-mail')
            ->expectsOutput('HTTP 401: Mailtrap rechazó el token. Usa un token de Email Sending válido.')
            ->doesntExpectOutputToContain('secret-token-and-response')
            ->assertFailed();
    }

    public function test_forbidden_request_reports_permissions_without_exposing_provider_details(): void
    {
        $this->sendingApi()->shouldReceive('send')->once()->andThrow(new HttpClientException('secret-token-and-response', 403));

        $this->artisan('send-mail')
            ->expectsOutput('HTTP 403: Revisa los permisos del token, el dominio verificado y las restricciones del destinatario.')
            ->doesntExpectOutputToContain('secret-token-and-response')
            ->assertFailed();
    }

    public function test_certificate_failure_reports_safe_configuration_guidance(): void
    {
        $this->sendingApi()->shouldReceive('send')->once()->andThrow(new RuntimeException('SSL certificate problem: secret-token-and-response'));

        $this->artisan('send-mail')
            ->expectsOutput('La conexión HTTPS falló al validar el certificado. Revisa curl.cainfo y openssl.cafile en el php.ini de consola.')
            ->doesntExpectOutputToContain('secret-token-and-response')
            ->assertFailed();
    }
}
