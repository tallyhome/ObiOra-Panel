<div @if($deployRunning) wire:poll.3s="pollDeploy" @endif>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <a href="{{ route('servers.index') }}" class="text-muted small text-decoration-none">&larr; Retour aux serveurs</a>
            <h1 class="h3 mb-1 mt-1">{{ $server->name }}</h1>
            <p class="text-muted mb-0">
                @if ($server->is_master)
                    <span class="badge text-bg-primary">Serveur maître</span>
                @endif
                <span class="badge text-bg-light text-dark">{{ $server->type->value }}</span>
                @if($server->os_name)
                    <span class="badge text-bg-secondary">{{ $server->os_name }}@if($server->os_version) {{ $server->os_version }}@endif</span>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @unless ($server->is_master)
                <button wire:click="ping" class="btn btn-outline-secondary btn-sm" wire:loading.attr="disabled">
                    Ping agent
                </button>
            @endunless
            <button wire:click="useServer" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                Utiliser ce serveur
            </button>
        </div>
    </div>

    @if($awaitingAgent)
        <div class="alert alert-info small mb-4 py-2">
            <strong>Étape suivante :</strong> installez l'agent seedbox via SSH ci-dessous (recommandé) ou avec la commande manuelle.
        </div>
    @endif

    @if($agentInstalled && $server->status->value === 'online')
        <div class="alert alert-success small mb-4 py-2">
            Agent seedbox connecté au panel.
            @if($canManage)
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" wire:click="toggleSshInstallPanel">
                    {{ $showSshInstallPanel ? 'Masquer' : 'Réafficher' }} l'installation SSH
                </button>
            @endif
        </div>
    @endif

    <div class="row g-3 obiora-server-detail-grid">
        <div class="col-xl-4 col-lg-5">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Informations</h2>
                    <dl class="row small mb-0">
                        <dt class="col-5">Hostname</dt>
                        <dd class="col-7 text-break">{{ $server->hostname }}</dd>
                        <dt class="col-5">Adresse IP</dt>
                        <dd class="col-7">{{ $server->ip_address }}</dd>
                        <dt class="col-5">Statut</dt>
                        <dd class="col-7">
                            @php
                                $badge = match($server->status->value) {
                                    'online' => 'success',
                                    'offline', 'error' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary',
                                };
                                $statusLabel = match($server->status->value) {
                                    'pending' => 'en attente agent',
                                    'online' => 'en ligne',
                                    default => $server->status->value,
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ $statusLabel }}</span>
                        </dd>
                        <dt class="col-5">OS</dt>
                        <dd class="col-7">{{ $server->os_name ?: '—' }} {{ $server->os_version }}</dd>
                        <dt class="col-5">Agents</dt>
                        <dd class="col-7">
                            <div class="d-flex flex-wrap gap-1">
                                @if($agentFlags['slave'])
                                    <span class="badge text-bg-primary">Seedbox</span>
                                @endif
                                @if($agentFlags['doctor'])
                                    <span class="badge text-bg-success">Doctor</span>
                                @endif
                                @if($agentFlags['crash'])
                                    <span class="badge text-bg-danger">Crash</span>
                                @endif
                                @if(!$agentFlags['any'])
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </dd>
                        <dt class="col-5">Dernière vue</dt>
                        <dd class="col-7">{{ $server->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        @unless ($server->is_master)
            <div class="col-xl-3 col-lg-4">
                <div class="card obiora-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Agent seedbox</h2>
                        <dl class="row small mb-0">
                            <dt class="col-5">Port</dt>
                            <dd class="col-7">{{ $server->primaryNode?->port ?? 9100 }}</dd>
                            <dt class="col-5">État</dt>
                            <dd class="col-7">
                                @if($agentInstalled)
                                    <span class="badge text-bg-success">Installé</span>
                                @else
                                    <span class="badge text-bg-secondary">À installer</span>
                                @endif
                            </dd>
                            <dt class="col-5">Dernier ping</dt>
                            <dd class="col-7">{{ $server->primaryNode?->last_ping_at?->diffForHumans() ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endunless

        @if($canManage && !$server->is_master && $showSshInstallPanel)
            <div class="col-xl-5 col-lg-12">
                <div class="card obiora-card border-primary h-100 obiora-ssh-install-panel">
                    <div class="card-body">
                        <h2 class="h6 mb-2">Installation SSH</h2>
                        <p class="small text-muted mb-2">Testez la connexion puis installez l'agent. Le token est envoyé automatiquement.</p>

                        <div class="row g-2">
                            <div class="col-12 col-sm-7">
                                <label class="form-label small mb-0">Hôte / IP</label>
                                <input type="text" wire:model.live="sshHost" class="form-control form-control-sm">
                            </div>
                            <div class="col-6 col-sm-2">
                                <label class="form-label small mb-0">Port</label>
                                <input type="number" wire:model.live="sshPort" class="form-control form-control-sm">
                            </div>
                            <div class="col-6 col-sm-3">
                                <label class="form-label small mb-0">User</label>
                                <input type="text" wire:model.live="sshUser" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0">Mot de passe SSH</label>
                                <input type="password" wire:model="sshPassword" class="form-control form-control-sm" autocomplete="new-password" placeholder="1ère installation uniquement">
                            </div>
                        </div>

                        @if($sshTestResult)
                            <div class="alert alert-{{ $sshTestOk ? 'success' : 'danger' }} py-1 small mt-2 mb-0">{{ $sshTestResult }}</div>
                        @endif

                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" wire:click="testSshConnection" class="btn btn-outline-secondary btn-sm" wire:loading.attr="disabled">Tester</button>
                            <button type="button" wire:click="deploySlaveAgent" class="btn btn-primary btn-sm" wire:loading.attr="disabled" @if($deployRunning) disabled @endif>
                                @if($deployRunning)
                                    <span class="spinner-border spinner-border-sm me-1"></span> Installation…
                                @else
                                    Installer l'agent
                                @endif
                            </button>
                        </div>

                        @if($deployRunning)
                            <div class="mt-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-truncate me-2">{{ $deployProgressMessage }}</span>
                                    <span>{{ $deployProgress }}%</span>
                                </div>
                                <div class="obiora-progress info mb-2" style="height: 6px;">
                                    <div class="bar" style="width: {{ max(2, $deployProgress) }}%"></div>
                                </div>
                                @if(!empty($deployConsole))
                                    <pre class="obiora-deploy-console small mb-0">@foreach($deployConsole as $line){{ $line }}
@endforeach</pre>
                                @endif
                            </div>
                        @endif

                        @if($deployError)
                            <p class="text-danger small mt-2 mb-0">{{ $deployError }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <details class="card obiora-card" @if(!$agentInstalled) open @endif>
                <summary class="card-header small fw-medium" style="cursor:pointer">Commandes manuelles (SSH)</summary>
                <div class="card-body">
                    @if ($server->agent_token)
                        <p class="small text-muted mb-2"><strong>1.</strong> Agent seedbox :</p>
                        <pre class="small bg-dark text-light p-2 rounded user-select-all mb-3 obiora-deploy-steps-pre"><code>{{ $slaveRemoteCommand }}</code></pre>
                        <p class="small text-muted mb-2"><strong>2.</strong> Doctor + Crash (via <a href="{{ route('doctor.index') }}">Doctor &amp; Suite</a>) :</p>
                        <pre class="small bg-dark text-light p-2 rounded user-select-all mb-0 obiora-deploy-steps-pre"><code>{{ $doctorRemoteCommand }}</code></pre>
                    @else
                        <p class="text-muted small mb-0">Aucun token agent.</p>
                    @endif
                </div>
            </details>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Diagnostics Doctor</h2>
                    @if ($server->latestDiagnosticReport)
                        <p class="mb-0 small">
                            Score <strong>{{ $server->latestDiagnosticReport->score }}%</strong>
                            — {{ $server->latestDiagnosticReport->generated_at?->format('d/m/Y H:i') }}
                        </p>
                    @else
                        <p class="text-muted small mb-0">
                            Installez Doctor via <a href="{{ route('doctor.index', ['server' => $server->id]) }}">Doctor &amp; Suite</a>
                            (IP {{ $server->ip_address }}).
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Crash Analyzer</h2>
                    <p class="text-muted small mb-0">
                        <a href="{{ route('crash-analyzer.index', ['server' => $server->id]) }}">Ouvrir Crash Analyzer</a>
                        pour ce serveur.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
