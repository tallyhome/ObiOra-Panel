<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MonitoringAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CriticalDiagnosticNotification extends Notification
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
        $server = $this->alert->server?->name ?? 'Serveur';

        return (new MailMessage)
            ->subject("[Obiora] {$this->alert->title}")
            ->greeting('Alerte monitoring Obiora')
            ->line("Serveur : {$server}")
            ->line($this->alert->message)
            ->action('Ouvrir le monitoring', url('/monitoring'))
            ->line('Consultez le tableau de bord pour plus de details.');
    }
}
