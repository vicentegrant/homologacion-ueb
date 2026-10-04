<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryPasswordNotification extends Notification
{
    public function __construct(public string $temporaryPassword) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Bienvenido al sistema de homologación UEB')
            ->view('mail.temporary-password', ['nombre' => $notifiable->nombres_completos, 'email' => $notifiable->email, 'password' => $this->temporaryPassword, 'url' => config('app.frontend_url'), 'expira' => $notifiable->temporary_password_expires_at?->format('d/m/Y H:i')]);
    }
}
