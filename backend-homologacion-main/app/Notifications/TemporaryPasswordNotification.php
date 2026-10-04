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
        return (new MailMessage)->subject('Acceso temporal al sistema de homologación')
            ->greeting('Hola, '.$notifiable->nombres_completos)
            ->line('Su cuenta ha sido creada. Usuario: '.$notifiable->email)
            ->line('Contraseña temporal: '.$this->temporaryPassword)
            ->line('Caduca en '.config('auth.temporary_password_hours').' horas. Al ingresar deberá cambiarla.')
            ->action('Ingresar', config('app.frontend_url'))
            ->line('Si caduca, utilice la opción Recuperar contraseña. No comparta este correo.');
    }
}
