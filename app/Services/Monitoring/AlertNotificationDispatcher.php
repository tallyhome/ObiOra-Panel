<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\AlertContact;
use App\Models\AlertPolicy;
use App\Models\AlertPolicyState;
use App\Models\Monitor;
use App\Models\MonitoringIncident;
use App\Models\NotificationLog;
use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class AlertNotificationDispatcher
{
    public function notify(MonitoringIncident $incident, AlertPolicy $policy): int
    {
        $contactIds = $policy->notify_contact_ids ?? [];

        if ($contactIds === []) {
            return 0;
        }

        $contacts = AlertContact::query()->whereIn('id', $contactIds)->get();
        $sent = 0;

        foreach ($contacts as $contact) {
            foreach ($contact->availableChannels() as $channel) {
                $result = $this->sendChannel($contact, $channel, $incident);

                NotificationLog::query()->create([
                    'monitoring_incident_id' => $incident->id,
                    'alert_contact_id' => $contact->id,
                    'channel' => $channel,
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'response' => $result['response'],
                    'sent_at' => now(),
                ]);

                if ($result['success']) {
                    $sent++;
                }
            }
        }

        $incident->forceFill(['last_notified_at' => now()])->save();

        return $sent;
    }

    /**
     * @return array{success: bool, response: string}
     */
    private function sendChannel(AlertContact $contact, string $channel, MonitoringIncident $incident): array
    {
        $subject = "[ObiOra] {$incident->trigger} — {$incident->resource_name}";
        $body = "{$incident->message}\n\nRessource: {$incident->resource_name}\nDébut: {$incident->went_down_at}";

        try {
            return match ($channel) {
                'email' => $this->sendEmail($contact->email, $subject, $body),
                'slack' => $this->sendWebhook($contact->slack_webhook, ['text' => $subject."\n".$body]),
                'discord' => $this->sendWebhook($contact->discord_webhook, ['content' => $subject."\n".$body]),
                'webhook' => $this->sendWebhook($contact->webhook_url, [
                    'incident_id' => $incident->id,
                    'trigger' => $incident->trigger,
                    'message' => $incident->message,
                    'resource' => $incident->resource_name,
                ]),
                'telegram' => $this->sendTelegram($contact->telegram_bot_token, $contact->telegram_chat_id, $subject."\n".$body),
                default => ['success' => false, 'response' => 'Canal inconnu'],
            };
        } catch (\Throwable $e) {
            Log::warning('Alert notification failed', ['channel' => $channel, 'error' => $e->getMessage()]);

            return ['success' => false, 'response' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, response: string}
     */
    private function sendEmail(?string $email, string $subject, string $body): array
    {
        if (! $email) {
            return ['success' => false, 'response' => 'Email vide'];
        }

        Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));

        return ['success' => true, 'response' => 'OK'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, response: string}
     */
    private function sendWebhook(?string $url, array $payload): array
    {
        if (! $url) {
            return ['success' => false, 'response' => 'URL vide'];
        }

        $response = Http::timeout(15)->post($url, $payload);

        return [
            'success' => $response->successful(),
            'response' => substr($response->body(), 0, 500),
        ];
    }

    /**
     * @return array{success: bool, response: string}
     */
    private function sendTelegram(?string $token, ?string $chatId, string $text): array
    {
        if (! $token || ! $chatId) {
            return ['success' => false, 'response' => 'Token/chat_id manquant'];
        }

        $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        return [
            'success' => $response->successful(),
            'response' => substr($response->body(), 0, 500),
        ];
    }
}
