<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CrashAnalyzerEvent;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CrashAnalyzerNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Server $server,
        private readonly CrashAnalyzerEvent $event,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[ObiOra] Crash Analyzer — '.$this->event->title)
            ->greeting('Alerte Crash Analyzer')
            ->line("Serveur : {$this->server->name} ({$this->server->hostname})")
            ->line("Type : {$this->event->event_type}")
            ->line("Sévérité : {$this->event->severity}")
            ->line($this->event->details)
            ->action('Voir le dashboard', url('/crash-analyzer?server='.$this->server->id));
    }
}
