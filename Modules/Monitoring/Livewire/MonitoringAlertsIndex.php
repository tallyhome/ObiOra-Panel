<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Enums\AlertPolicyOperator;
use App\Models\AlertContact;
use App\Models\AlertPolicy;
use App\Models\NotificationLog;
use App\Services\Monitoring\AlertNotificationDispatcher;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Alertes')]
final class MonitoringAlertsIndex extends Component
{
    public string $activeTab = 'policies';

    public bool $showPolicyModal = false;

    public bool $showContactModal = false;

    public ?int $editingPolicyId = null;

    public ?int $editingContactId = null;

    public string $policyName = '';

    public string $policyMetric = 'disk_usage_percent';

    public string $policyOperator = 'gt';

    public string $policyValue = '90';

    public string $policyValueUnit = '%';

    public int $policyDurationMinutes = 15;

    public int $policyRepeatMinutes = 60;

    public string $policyApplyTo = 'all';

    /** @var list<int> */
    public array $policyContactIds = [];

    public string $policyDescription = '';

    public string $contactName = '';

    public string $contactEmail = '';

    public string $contactSlackWebhook = '';

    public string $contactDiscordWebhook = '';

    public string $contactTelegramToken = '';

    public string $contactTelegramChatId = '';

    public string $contactWebhookUrl = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        if (request()->routeIs('monitoring.alerts.contacts')) {
            $this->activeTab = 'contacts';
        }

        if (request()->routeIs('monitoring.alerts.notifications')) {
            $this->activeTab = 'notifications';
        }
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openPolicyModal(?int $policyId = null): void
    {
        $this->authorizeManage();

        if ($policyId !== null) {
            $policy = AlertPolicy::query()->findOrFail($policyId);
            $this->editingPolicyId = $policy->id;
            $this->policyName = $policy->name;
            $this->policyMetric = $policy->metric;
            $this->policyOperator = $policy->operator instanceof AlertPolicyOperator
                ? $policy->operator->value
                : (string) $policy->operator;
            $this->policyValue = (string) $policy->value;
            $this->policyValueUnit = $policy->value_unit ?? '';
            $this->policyDurationMinutes = $policy->duration_minutes;
            $this->policyRepeatMinutes = $policy->repeat_minutes;
            $this->policyApplyTo = $policy->apply_to;
            $this->policyContactIds = $policy->notify_contact_ids ?? [];
            $this->policyDescription = $policy->description ?? '';
        } else {
            $this->resetPolicyForm();
            $this->editingPolicyId = null;
            $defaultContact = AlertContact::query()->where('is_default', true)->first();
            $this->policyContactIds = $defaultContact ? [$defaultContact->id] : [];
        }

        $this->showPolicyModal = true;
    }

    public function savePolicy(): void
    {
        $this->authorizeManage();

        $this->validate([
            'policyName' => ['required', 'string', 'max:255'],
            'policyMetric' => ['required', 'string', 'max:64'],
            'policyOperator' => ['required', 'in:'.implode(',', array_column(AlertPolicyOperator::cases(), 'value'))],
            'policyValue' => ['required', 'numeric'],
            'policyDurationMinutes' => ['required', 'integer', 'min:0'],
            'policyRepeatMinutes' => ['required', 'integer', 'min:0'],
            'policyApplyTo' => ['required', 'in:all,servers,monitors'],
            'policyContactIds' => ['required', 'array', 'min:1'],
            'policyContactIds.*' => ['integer', 'exists:alert_contacts,id'],
        ]);

        $data = [
            'name' => $this->policyName,
            'metric' => $this->policyMetric,
            'operator' => $this->policyOperator,
            'value' => (float) $this->policyValue,
            'value_unit' => $this->policyValueUnit ?: null,
            'duration_minutes' => $this->policyDurationMinutes,
            'repeat_minutes' => $this->policyRepeatMinutes,
            'apply_to' => $this->normalizedApplyTo(),
            'apply_target_ids' => null,
            'notify_contact_ids' => array_values(array_map('intval', $this->policyContactIds)),
            'description' => $this->policyDescription ?: null,
            'is_enabled' => true,
        ];

        if ($this->editingPolicyId !== null) {
            AlertPolicy::query()->whereKey($this->editingPolicyId)->update($data);
            $this->dispatch('notify', type: 'success', message: 'Politique mise à jour.');
        } else {
            AlertPolicy::query()->create($data);
            $this->dispatch('notify', type: 'success', message: 'Politique créée.');
        }

        $this->showPolicyModal = false;
        $this->resetPolicyForm();
    }

    public function togglePolicy(int $policyId): void
    {
        $this->authorizeManage();
        $policy = AlertPolicy::query()->findOrFail($policyId);
        $policy->update(['is_enabled' => ! $policy->is_enabled]);
    }

    public function deletePolicy(int $policyId): void
    {
        $this->authorizeManage();
        AlertPolicy::query()->whereKey($policyId)->delete();
        $this->dispatch('notify', type: 'success', message: 'Politique supprimée.');
    }

    public function openContactModal(?int $contactId = null): void
    {
        $this->authorizeManage();

        if ($contactId !== null) {
            $contact = AlertContact::query()->findOrFail($contactId);
            $this->editingContactId = $contact->id;
            $this->contactName = $contact->name;
            $this->contactEmail = $contact->email ?? '';
            $this->contactSlackWebhook = $contact->slack_webhook ?? '';
            $this->contactDiscordWebhook = $contact->discord_webhook ?? '';
            $this->contactTelegramToken = $contact->telegram_bot_token ?? '';
            $this->contactTelegramChatId = $contact->telegram_chat_id ?? '';
            $this->contactWebhookUrl = $contact->webhook_url ?? '';
        } else {
            $this->resetContactForm();
            $this->editingContactId = null;
        }

        $this->showContactModal = true;
    }

    public function saveContact(): void
    {
        $this->authorizeManage();

        $this->validate([
            'contactName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactSlackWebhook' => ['nullable', 'url', 'max:2048'],
            'contactDiscordWebhook' => ['nullable', 'url', 'max:2048'],
            'contactWebhookUrl' => ['nullable', 'url', 'max:2048'],
            'contactTelegramToken' => ['nullable', 'string', 'max:255'],
            'contactTelegramChatId' => ['nullable', 'string', 'max:64'],
        ]);

        $data = [
            'name' => $this->contactName,
            'email' => $this->contactEmail ?: null,
            'slack_webhook' => $this->contactSlackWebhook ?: null,
            'discord_webhook' => $this->contactDiscordWebhook ?: null,
            'telegram_bot_token' => $this->contactTelegramToken ?: null,
            'telegram_chat_id' => $this->contactTelegramChatId ?: null,
            'webhook_url' => $this->contactWebhookUrl ?: null,
        ];

        if ($this->editingContactId !== null) {
            AlertContact::query()->whereKey($this->editingContactId)->update($data);
            $this->dispatch('notify', type: 'success', message: 'Contact mis à jour.');
        } else {
            AlertContact::query()->create($data + ['is_default' => false]);
            $this->dispatch('notify', type: 'success', message: 'Contact créé.');
        }

        $this->showContactModal = false;
        $this->resetContactForm();
    }

    public function deleteContact(int $contactId): void
    {
        $this->authorizeManage();

        $contact = AlertContact::query()->findOrFail($contactId);

        if ($contact->is_default) {
            $this->dispatch('notify', type: 'error', message: 'Le contact par défaut ne peut pas être supprimé.');

            return;
        }

        $contact->delete();
        $this->dispatch('notify', type: 'success', message: 'Contact supprimé.');
    }

    public function testContact(int $contactId, AlertNotificationDispatcher $dispatcher): void
    {
        $this->authorizeManage();

        $contact = AlertContact::query()->findOrFail($contactId);

        if ($contact->availableChannels() === []) {
            $this->dispatch('notify', type: 'error', message: 'Aucun canal configuré sur ce contact.');

            return;
        }

        $sent = $dispatcher->sendTest($contact);
        $this->dispatch('notify', type: $sent > 0 ? 'success' : 'error', message: $sent > 0
            ? "Test envoyé sur {$sent} canal(aux)."
            : 'Échec envoi test — voir les logs de notification.');
    }

    public function render()
    {
        $policies = AlertPolicy::query()
            ->orderBy('name')
            ->get()
            ->map(fn (AlertPolicy $policy) => [
                'id' => $policy->id,
                'name' => $policy->name,
                'condition' => $policy->conditionLabel(),
                'duration' => $policy->duration_minutes === 0 ? 'Immédiat' : $policy->duration_minutes.' min',
                'repeat' => $policy->repeat_minutes === 0 ? '—' : $policy->repeat_minutes.' min',
                'apply_to' => $policy->apply_to,
                'is_enabled' => $policy->is_enabled,
                'description' => $policy->description,
            ]);

        $contacts = AlertContact::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (AlertContact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'channels' => $contact->availableChannels(),
                'is_default' => $contact->is_default,
            ]);

        return view('monitoring::livewire.monitoring-alerts-index', [
            'policies' => $policies,
            'contacts' => $contacts,
            'metricChoices' => $this->metricChoices(),
            'operatorChoices' => AlertPolicyOperator::cases(),
            'applyToChoices' => $this->applyToChoices(),
            'allContacts' => AlertContact::query()->orderBy('name')->get(['id', 'name']),
            'notificationLogs' => NotificationLog::query()
                ->with(['contact:id,name', 'incident:id,trigger,resource_name'])
                ->orderByDesc('sent_at')
                ->limit(100)
                ->get()
                ->map(fn (NotificationLog $log) => [
                    'id' => $log->id,
                    'contact' => $log->contact?->name ?? '—',
                    'channel' => $log->channel,
                    'status' => $log->status,
                    'response' => \Illuminate\Support\Str::limit((string) $log->response, 80),
                    'sent_at' => UserTimezone::format($log->sent_at, 'd/m/Y H:i:s'),
                    'incident' => $log->incident
                        ? $log->incident->trigger.' — '.$log->incident->resource_name
                        : 'Test',
                ]),
            'canManage' => auth()->user()?->can('monitoring.manage') ?? false,
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);
    }

    private function normalizedApplyTo(): string
    {
        if (in_array($this->policyMetric, ['monitor_status', 'ssl_expiry_days'], true)) {
            return 'monitors';
        }

        if (in_array($this->policyMetric, [
            'cpu_usage_percent', 'cpu_steal_percent', 'memory_usage_percent',
            'disk_usage_percent', 'load_per_core', 'uptime_seconds', 'agent_no_data_minutes',
        ], true) && $this->policyApplyTo === 'monitors') {
            return 'servers';
        }

        return $this->policyApplyTo;
    }

    private function resetPolicyForm(): void
    {
        $this->policyName = '';
        $this->policyMetric = 'disk_usage_percent';
        $this->policyOperator = 'gt';
        $this->policyValue = '90';
        $this->policyValueUnit = '%';
        $this->policyDurationMinutes = 15;
        $this->policyRepeatMinutes = 60;
        $this->policyApplyTo = 'all';
        $this->policyContactIds = [];
        $this->policyDescription = '';
    }

    private function resetContactForm(): void
    {
        $this->contactName = '';
        $this->contactEmail = '';
        $this->contactSlackWebhook = '';
        $this->contactDiscordWebhook = '';
        $this->contactTelegramToken = '';
        $this->contactTelegramChatId = '';
        $this->contactWebhookUrl = '';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function metricChoices(): array
    {
        return [
            ['value' => 'cpu_usage_percent', 'label' => 'CPU usage (%)'],
            ['value' => 'cpu_steal_percent', 'label' => 'CPU steal (%)'],
            ['value' => 'memory_usage_percent', 'label' => 'Memory usage (%)'],
            ['value' => 'disk_usage_percent', 'label' => 'Disk usage (%)'],
            ['value' => 'load_per_core', 'label' => 'Load per core'],
            ['value' => 'uptime_seconds', 'label' => 'Uptime (seconds)'],
            ['value' => 'agent_no_data_minutes', 'label' => 'No agent data (minutes)'],
            ['value' => 'monitor_status', 'label' => 'Monitor status (0=down)'],
            ['value' => 'ssl_expiry_days', 'label' => 'SSL expiry (days)'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function applyToChoices(): array
    {
        return [
            ['value' => 'all', 'label' => 'Tous (serveurs + moniteurs)'],
            ['value' => 'servers', 'label' => 'Serveurs uniquement'],
            ['value' => 'monitors', 'label' => 'Moniteurs uniquement'],
        ];
    }
}
