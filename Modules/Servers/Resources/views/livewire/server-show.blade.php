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

    @if($server->status->value === 'pending' && !$server->is_master)
        <div class="alert alert-warning small mb-4">
            <strong>Agent non connecté.</strong> Le serveur est enregistré dans le panel mais l'agent seedbox n'a pas encore répondu.
            Utilisez l'installation SSH ci-dessous ou installez manuellement l'agent puis cliquez sur « Ping agent ».
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Informations</h2>
                    <dl class="row small mb-0">
                        <dt class="col-4">Hostname</dt>
                        <dd class="col-8">{{ $server->hostname }}</dd>
                        <dt class="col-4">Adresse IP</dt>
                        <dd class="col-8">{{ $server->ip_address }}</dd>
                        <dt class="col-4">Statut</dt>
                        <dd class="col-8">
                            @php
                                $badge = match($server->status->value) {
                                    'online' => 'success',
                                    'offline', 'error' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary',
                                };
                                $statusLabel = match($server->status->value) {
                                    'pending' => 'en attente agent',
                                    default => $server->status->value,
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ $statusLabel }}</span>
                        </dd>
                        <dt class="col-4">OS</dt>
                        <dd class="col-8">{{ $server->os_name ?? '—' }} {{ $server->os_version }}</dd>
                        <dt class="col-4">Dernière vue</dt>
                        <dd class="col-8">{{ $server->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        @php $doctor = $server->metadata['doctor'] ?? null; @endphp
                        @if ($doctor)
                            <dt class="col-4">Obiora Doctor</dt>
                            <dd class="col-8">
                                <span class="badge text-bg-{{ ($doctor['score'] ?? 0) >= 90 ? 'success' : 'warning' }}">
                                    {{ $doctor['score'] ?? '—' }}%
                                </span>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        @unless ($server->is_master)
            <div class="col-lg-6">
                <div class="card obiora-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Agent seedbox slave</h2>
                        <p class="small text-muted mb-2">Port HTTP de l'agent PHP sur ce VPS (défaut 9100).</p>
                        <dl class="row small mb-0">
                            <dt class="col-4">Port</dt>
                            <dd class="col-8">{{ $server->primaryNode?->port ?? 9100 }}</dd>
                            <dt class="col-4">Agent</dt>
                            <dd class="col-8">
                                @if($agentInstalled || $server->status->value === 'online')
                                    <span class="badge text-bg-success">Installé</span>
                                @else
                                    <span class="badge text-bg-secondary">Non installé</span>
                                @endif
                            </dd>
                            <dt class="col-4">Dernier ping</dt>
                            <dd class="col-8">{{ $server->primaryNode?->last_ping_at?->diffForHumans() ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endunless

        @if($canManage && !$server->is_master)
            <div class="col-12">
                <div class="card obiora-card border-primary">
                    <div class="card-body">
                        <h2 class="h6 mb-2">Installation automatique (SSH)</h2>
                        <p class="small text-muted mb-3">
                            Comme Doctor &amp; Suite : testez la connexion SSH, puis installez l'agent seedbox slave sur le VPS.
                            Le token panel est transmis automatiquement — aucune copie manuelle.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Hôte / IP</label>
                                <input type="text" wire:model.live="sshHost" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Port SSH</label>
                                <input type="number" wire:model.live="sshPort" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Utilisateur</label>
                                <input type="text" wire:model.live="sshUser" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Mot de passe SSH</label>
                                <input type="password" wire:model="sshPassword" class="form-control form-control-sm" autocomplete="new-password" placeholder="root (1ère fois)">
                            </div>
                        </div>

                        @if($sshTestResult)
                            <div class="alert alert-{{ $sshTestOk ? 'success' : 'danger' }} py-2 small mt-3 mb-0">{{ $sshTestResult }}</div>
                        @endif

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" wire:click="testSshConnection" class="btn btn-outline-secondary btn-sm" wire:loading.attr="disabled">
                                Tester la connexion
                            </button>
                            <button type="button" wire:click="deploySlaveAgent" class="btn btn-primary btn-sm" wire:loading.attr="disabled" @if($deployRunning) disabled @endif>
                                @if($deployRunning)
                                    <span class="spinner-border spinner-border-sm me-1"></span> Installation…
                                @else
                                    Installer l'agent seedbox
                                @endif
                            </button>
                        </div>

                        @if($deployRunning || $deployFinished)
                            <div class="mt-4">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>{{ $deployProgressMessage }}</span>
                                    <span>{{ $deployProgress }}%</span>
                                </div>
                                <div class="obiora-progress info mb-2" style="height: 8px;">
                                    <div class="bar" style="width: {{ max(2, $deployProgress) }}%"></div>
                                </div>
                                @if(!empty($deployConsole))
                                    <pre class="small bg-dark text-light p-3 rounded mb-0" style="max-height: 200px; overflow: auto;">@foreach($deployConsole as $line){{ $line }}
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
            <div class="card obiora-card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Commandes manuelles (SSH sur le VPS)</h2>
                    @if ($server->agent_token)
                        <p class="small text-muted mb-2"><strong>1.</strong> Agent seedbox slave :</p>
                        <pre class="small bg-dark text-light p-3 rounded user-select-all mb-3"><code>{{ $slaveRemoteCommand }}</code></pre>

                        <p class="small text-muted mb-2"><strong>2.</strong> Agent Obiora Doctor (optionnel) :</p>
                        <pre class="small bg-dark text-light p-3 rounded user-select-all mb-3"><code>{{ $doctorRemoteCommand }}</code></pre>

                        <details class="small">
                            <summary class="text-muted">Token agent &amp; clé Doctor</summary>
                            <dl class="row mt-2 mb-0">
                                <dt class="col-sm-3">Token agent</dt>
                                <dd class="col-sm-9">
                                    <code class="user-select-all">{{ $server->agent_token }}</code>
                                    @if($canManage)
                                        <button type="button" class="btn btn-outline-warning btn-sm ms-2 py-0"
                                            wire:click="regenerateAgentToken"
                                            wire:confirm="Régénérer le token ? Les agents devront être reconfigurés.">
                                            Régénérer
                                        </button>
                                    @endif
                                </dd>
                                @if (!empty($server->metadata['doctor_signing_key']))
                                    <dt class="col-sm-3">Clé signature Doctor</dt>
                                    <dd class="col-sm-9"><code class="user-select-all">{{ $server->metadata['doctor_signing_key'] }}</code></dd>
                                @endif
                            </dl>
                        </details>
                    @else
                        <p class="text-muted small mb-0">Aucun token agent.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card obiora-card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Diagnostics Obiora Doctor</h2>
                    @if ($server->latestDiagnosticReport)
                        <p class="mb-2">
                            Score: <strong>{{ $server->latestDiagnosticReport->score }}%</strong>
                            — {{ $server->latestDiagnosticReport->generated_at?->format('d/m/Y H:i') }}
                        </p>
                    @else
                        <p class="text-muted small mb-0">
                            Installez l'agent Doctor (commande ci-dessus) ou via <a href="{{ route('doctor.index') }}">Doctor &amp; Suite</a>.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
