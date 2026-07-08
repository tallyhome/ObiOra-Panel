<?php

declare(strict_types=1);

namespace App\Services\CrashAnalyzer;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use App\Models\User;
use App\Notifications\CrashAnalyzerNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class CrashAnalyzerNotificationService
{
    public function notifyCrash(Server $server, CrashAnalyzerEvent $event): void
    {
        $config = config('crash_analyzer.notifications', []);

        if ($config['email'] ?? true) {
            $users = User::query()->get();
            if ($users->isNotEmpty()) {
                Notification::send($users, new CrashAnalyzerNotification($server, $event));
            }
        }

        $message = $this->formatMessage($server, $event);

        if (($config['discord'] ?? false) && ! empty($config['discord_webhook'])) {
            $this->sendWebhook((string) $config['discord_webhook'], [
                'content' => $message,
            ]);
        }

        if (($config['telegram'] ?? false) && ! empty($config['telegram_bot_token']) && ! empty($config['telegram_chat_id'])) {
            Http::timeout(10)->post(
                'https://api.telegram.org/bot'.$config['telegram_bot_token'].'/sendMessage',
                ['chat_id' => $config['telegram_chat_id'], 'text' => $message, 'parse_mode' => 'HTML'],
            );
        }

        if (($config['slack'] ?? false) && ! empty($config['slack_webhook'])) {
            $this->sendWebhook((string) $config['slack_webhook'], ['text' => $message]);
        }

        if (($config['webhook'] ?? false) && ! empty($config['webhook_url'])) {
            $this->sendWebhook((string) $config['webhook_url'], [
                'server_id' => $server->id,
                'event' => $event->toArray(),
                'message' => $message,
            ]);
        }

        $event->forceFill(['notified' => true])->save();
    }

    public function notifyReport(Server $server, CrashAnalyzerReport $report): void
    {
        $config = config('crash_analyzer.notifications', []);
        $message = "Rapport Crash Analyzer — {$server->name} ({$report->trigger_type})";

        if (($config['webhook'] ?? false) && ! empty($config['webhook_url'])) {
            $this->sendWebhook((string) $config['webhook_url'], [
                'type' => 'crash_report',
                'server_id' => $server->id,
                'report_id' => $report->id,
                'message' => $message,
            ]);
        }
    }

    private function formatMessage(Server $server, CrashAnalyzerEvent $event): string
    {
        return sprintf(
            '🚨 Crash Analyzer — %s (%s)\n%s: %s\n%s',
            $server->name,
            $server->hostname,
            strtoupper($event->severity),
            $event->title,
            $event->details,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(string $url, array $payload): void
    {
        try {
            Http::timeout(10)->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Crash Analyzer webhook failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

}
