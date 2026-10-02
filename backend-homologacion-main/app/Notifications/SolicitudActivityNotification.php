<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SolicitudActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $solicitudId, public string $event, public string $message) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('notifications.student_mail_enabled')
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Actualización de solicitud de homologación')
            ->line($this->message)
            ->action('Consultar solicitud', rtrim(config('app.frontend_url'), '/').'/solicitudes/'.$this->solicitudId);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'solicitud_id' => $this->solicitudId,
            'evento' => $this->event,
            'mensaje' => $this->message,
            'url' => route('student.solicitudes.show', $this->solicitudId, false),
        ];
    }
}
