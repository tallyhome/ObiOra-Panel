<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MonitoringAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SslExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly MonitoringAlert $alert) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $host = (string) ($this->alert->payload['host'] ?? '');

        return (new MailMessage)
            ->subject("[Obiora] Certificat SSL {$host}")
            ->greeting('Alerte certificat SSL')
            ->line($this->alert->message)
            ->action('Voir le serveur', url('/monitoring'))
            ->line('Renouvelez le certificat (certbot / Let\'s Encrypt) avant expiration.');
    }
}
