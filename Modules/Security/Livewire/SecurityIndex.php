<?php

declare(strict_types=1);

namespace Modules\Security\Livewire;

use App\Jobs\Security\TriggerSecurityScanJob;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\Security\SecurityAuditService;
use App\Services\Security\SecurityRemediationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sécurité serveur')]
final class SecurityIndex extends Component
{
    #[Url(as: 'server')]
    public ?int $serverId = null;

    /** @var array<string, mixed> */
    public array $audit = [];

    /** @var list<array<string, mixed>> */
    public array $fleet = [];

    public bool $scanning = false;

    public ?string $actionMessage = null;

    public bool $actionOk = false;

    public function mount(ServerManager $servers, SecurityAuditService $auditService): void
    {
        abort_unless(auth()->user()?->can('modules.view'), 403);

        $eligible = $servers->all()->filter(fn (Server $s) => $auditService->isEligible($s));

        if ($eligible->isEmpty()) {
            $this->fleet = [];

            return;
        }

        if ($this->serverId === null || ! $eligible->contains('id', $this->serverId)) {
            $master = $eligible->firstWhere('is_master', true);
            $this->serverId = ($master ?? $eligible->first())?->id;
        }

        $this->refreshAudit($auditService, $servers);
    }

    public function updatedServerId(ServerManager $servers, SecurityAuditService $auditService): void
    {
        $this->refreshAudit($auditService, $servers);
    }

    public function refreshAudit(SecurityAuditService $auditService, ServerManager $servers): void
    {
        $this->actionMessage = null;
        $eligible = $servers->all()->filter(fn (Server $s) => $auditService->isEligible($s));
        $this->fleet = $auditService->fleetOverview($eligible);

        $server = $eligible->firstWhere('id', $this->serverId);
        $this->audit = $server ? $auditService->serverAudit($server) : [];
    }

    public function runScan(ServerManager $servers, SecurityAuditService $auditService): void
    {
        abort_unless(auth()->user()?->can('modules.manage'), 403);

        $server = $this->currentServer($servers, $auditService);
        if ($server === null) {
            $this->dispatch('notify', type: 'danger', message: 'Serveur non éligible.');

            return;
        }

        $this->scanning = true;
        TriggerSecurityScanJob::dispatch($server->id);
        $this->scanning = false;
        $this->actionOk = true;
        $this->actionMessage = 'Scan sécurité lancé en arrière-plan. Actualisez la page dans 1 à 2 minutes.';

        $this->dispatch('notify', type: 'success', message: $this->actionMessage);
    }

    public function applyHardening(string $action, SecurityRemediationService $remediation, ServerManager $servers, SecurityAuditService $auditService): void
    {
        abort_unless(auth()->user()?->can('modules.manage'), 403);

        $server = $this->currentServer($servers, $auditService);
        if ($server === null) {
            $this->dispatch('notify', type: 'danger', message: 'Serveur non éligible.');

            return;
        }

        $result = $remediation->apply($server, $action);
        $this->actionOk = $result['success'];
        $this->actionMessage = $result['message'];

        $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['message']);
        $this->refreshAudit($auditService, $servers);
    }

    public function render(SecurityRemediationService $remediation, ServerManager $servers, SecurityAuditService $auditService)
    {
        $eligible = $servers->all()->filter(fn (Server $s) => $auditService->isEligible($s));
        $server = $eligible->firstWhere('id', $this->serverId);

        return view('security::livewire.security-index', [
            'server' => $server,
            'eligibleServers' => $eligible,
            'hardenActions' => $remediation->availableActions(),
            'canManage' => auth()->user()?->can('modules.manage') ?? false,
        ]);
    }

    private function currentServer(ServerManager $servers, SecurityAuditService $auditService): ?Server
    {
        return $servers->all()
            ->filter(fn (Server $s) => $auditService->isEligible($s))
            ->firstWhere('id', $this->serverId);
    }
}
