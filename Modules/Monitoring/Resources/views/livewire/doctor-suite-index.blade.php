<div @if($deployRunning) wire:poll.2s="pollDeploy" @endif>
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h3 mb-1">ObiOra Doctor & Suite</h1>
            <p class="text-muted mb-0">Diagnostic, Crash Analyzer et déploiement distant en un clic — sans stocker vos identifiants SSH.</p>
        </div>
        @if($server)
        <div class="d-flex gap-2">
            <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary btn-sm">Monitoring</a>
            <a href="{{ route('crash-analyzer.index', ['server' => $server->id]) }}" class="btn btn-outline-danger btn-sm">Crash Analyzer</a>
        </div>
        @endif
    </div>

    <div class="alert alert-secondary py-2 small mb-4">
        <strong>Principe sécurisé</strong> — Le panel génère une <strong>clé SSH dédiée</strong> (privée chiffrée).
        Le mot de passe n'est utilisé qu'<em>une fois</em> pour installer la clé publique sur le VPS.
        Ensuite : clé SSH + <code>OBIORA_AGENT_TOKEN</code> (API), sans mot de passe stocké.
        @if($reportCount > 0)
            <span class="ms-2 text-success">· {{ $reportCount }} rapport(s) Doctor — dernier {{ $lastReportLabel }}</span>
        @endif
    </div>

    <div class="card obiora-card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Serveur panel</label>
                    <select wire:model.live="serverId" class="form-select">
                        @foreach($doctorFleet as $fleetServer)
                            <option value="{{ $fleetServer->id }}">{{ $fleetServer->name }} (ID {{ $fleetServer->id }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 small text-muted">
                    Token agent (API) : <code>{{ $server?->agent_token ? Str::limit($server->agent_token, 20).'…' : '—' }}</code>
                    — utilisé après installation, à la place du mot de passe SSH.
                </div>
            </div>
        </div>
    </div>

    @if(!empty($fleetOverview))
    <div class="card obiora-card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Vue flotte — Doctor & Crash Analyzer</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Serveur</th>
                            <th>Doctor</th>
                            <th>Crash Analyzer</th>
                            <th>Critiques 24h</th>
                            <th>Rapports crash</th>
                            <th>Déployé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fleetOverview as $row)
                        <tr @class(['table-active' => $row['id'] === $server?->id])>
                            <td>{{ $row['name'] }}<br><span class="small text-muted">{{ $row['hostname'] }}</span></td>
                            <td>
                                @if($row['doctor_score'] !== null)
                                    <span class="badge text-bg-success">{{ $row['doctor_score'] }}%</span>
                                    <span class="small text-muted d-block">{{ $row['doctor_status'] }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $row['crash_last_metric_at'] ? \Illuminate\Support\Carbon::parse($row['crash_last_metric_at'])->diffForHumans() : '—' }}
                            </td>
                            <td>
                                @if($row['crash_critical_24h'] > 0)
                                    <span class="badge text-bg-danger">{{ $row['crash_critical_24h'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>{{ $row['crash_reports'] }}</td>
                            <td>{{ $row['deployed'] ? '✔' : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @if($deployRunning)
    <div class="card obiora-card mb-4 border-primary">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-medium">Déploiement Doctor & Suite en cours…</span>
                <span class="small text-muted">{{ $deployProgress }}%</span>
            </div>
            <div class="obiora-progress info mb-2">
                <div class="bar" style="width: {{ max(3, $deployProgress) }}%"></div>
            </div>
            <p class="small text-muted mb-0">{{ $deployProgressMessage ?: 'Veuillez patienter…' }}</p>
        </div>
    </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card obiora-card h-100 border-primary">
                <div class="card-header bg-primary bg-opacity-10">Déploiement automatique (SSH)</div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        <strong>Étape 1</strong> — Générer la clé ·
                        <strong>Étape 2</strong> — Installer la clé (mot de passe une fois) ·
                        <strong>Étape 3</strong> — Tester ·
                        <strong>Étape 4</strong> — Déployer
                    </p>

                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small">Hôte</label>
                            <input type="text" wire:model="sshHost" class="form-control form-control-sm" placeholder="IP ou hostname">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Port</label>
                            <input type="number" wire:model="sshPort" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Utilisateur</label>
                            <input type="text" wire:model="sshUser" class="form-control form-control-sm" placeholder="root">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Mot de passe <span class="text-muted">(bootstrap uniquement)</span></label>
                            <input type="password" wire:model="sshPassword" class="form-control form-control-sm" autocomplete="off">
                            @error('sshPassword') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    @can('servers.manage')
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" wire:click="generateSshKey" wire:loading.attr="disabled" class="btn btn-outline-secondary btn-sm">
                            ① Générer clé SSH
                        </button>
                        <button type="button" wire:click="bootstrapSshKey" wire:loading.attr="disabled" class="btn btn-outline-secondary btn-sm" @if(!$sshPublicKey) disabled @endif>
                            ② Installer clé sur VPS
                        </button>
                        <button type="button" wire:click="testSshConnection" wire:loading.attr="disabled" class="btn btn-outline-info btn-sm">
                            Tester connexion SSH
                        </button>
                    </div>
                    @endcan

                    @if($sshPublicKey)
                    <div class="mb-3">
                        <label class="form-label small">Clé publique (panel)</label>
                        <div class="obiora-copy-block">
                            <pre class="small mb-0 obiora-copy-text text-break">{{ $sshPublicKey }}</pre>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="obioraCopyFromButton(this)">Copier</button>
                        </div>
                        @if($sshKeyInstalled)
                            <span class="badge text-bg-success mt-1">✔ Clé installée sur le VPS</span>
                        @else
                            <span class="badge text-bg-warning text-dark mt-1">Clé non installée sur le VPS</span>
                        @endif
                    </div>
                    @endif

                    @if($sshBootstrapResult)
                    <div class="alert alert-info py-2 small">{{ $sshBootstrapResult }}</div>
                    @endif

                    @if($sshTestResult)
                    <div class="alert py-2 small {{ $sshTestOk ? 'alert-success' : 'alert-danger' }}">
                        <pre class="small mb-0" style="white-space:pre-wrap">{{ $sshTestResult }}</pre>
                    </div>
                    @endif

                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" wire:model="deployDoctor" id="deployDoctor">
                            <label class="form-check-label small" for="deployDoctor">ObiOra Doctor</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" wire:model="deployCrashAnalyzer" id="deployCrash">
                            <label class="form-check-label small" for="deployCrash">Crash Analyzer</label>
                        </div>
                    </div>

                    @can('servers.manage')
                    <button type="button" wire:click="deployRemote" wire:loading.attr="disabled" class="btn btn-primary btn-sm"
                            @if($deployRunning || !$sshKeyInstalled) disabled @endif>
                        <span wire:loading.remove wire:target="deployRemote">④ Installer à distance</span>
                        <span wire:loading wire:target="deployRemote">Lancement…</span>
                    </button>
                    @if(!$sshKeyInstalled)
                        <p class="small text-muted mt-2 mb-0">Installez la clé SSH sur le VPS avant le déploiement automatique.</p>
                    @endif
                    @else
                    <p class="small text-muted mb-0">Permission <code>servers.manage</code> requise.</p>
                    @endcan

                    @if($deployError)
                    <div class="alert alert-danger py-2 small mt-3 mb-0">{{ $deployError }}</div>
                    @endif

                    @foreach($deploySteps as $step)
                    <div class="mt-3">
                        <strong class="small {{ $step['success'] ? 'text-success' : 'text-danger' }}">
                            {{ $step['component'] }} — {{ $step['success'] ? 'OK' : 'Échec' }}
                        </strong>
                        <pre class="small bg-dark text-light p-2 rounded mt-1 mb-0" style="max-height:120px;overflow:auto">{{ $step['output'] }}</pre>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header">Installation manuelle</div>
                <div class="card-body">
                    <h3 class="h6">Tout-en-un (Doctor + Crash Analyzer)</h3>
                    <div class="obiora-copy-block mb-3">
                        <pre class="small mb-0 obiora-copy-text">{{ $remoteSuiteInstall }}</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="obioraCopyFromButton(this)">Copier</button>
                    </div>
                    <h3 class="h6">Doctor seul</h3>
                    <div class="obiora-copy-block">
                        <pre class="small mb-0 obiora-copy-text">{{ $remoteInstall }}</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="obioraCopyFromButton(this)">Copier</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($overview)
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header">Résultats Doctor — {{ $server?->name }}</div>
                <div class="card-body">
                    @if($overview['doctor'])
                        <dl class="row small mb-3">
                            <dt class="col-sm-4">Score</dt>
                            <dd class="col-sm-8"><span class="badge text-bg-success">{{ $overview['doctor']['score'] }}%</span></dd>
                            <dt class="col-sm-4">Statut</dt>
                            <dd class="col-sm-8">{{ $overview['doctor']['status'] }}</dd>
                            <dt class="col-sm-4">Version</dt>
                            <dd class="col-sm-8">{{ $overview['doctor']['doctor_version'] ?: '—' }}</dd>
                            <dt class="col-sm-4">Généré</dt>
                            <dd class="col-sm-8">{{ $overview['doctor']['generated_at'] ?? '—' }}</dd>
                        </dl>

                        @if(!empty($overview['doctor']['critical_findings']))
                        <h3 class="h6 text-danger">Findings critiques</h3>
                        <ul class="small mb-3">
                            @foreach($overview['doctor']['critical_findings'] as $finding)
                            <li><strong>{{ $finding['module'] ?? '' }}</strong> — {{ $finding['title'] ?? '' }}</li>
                            @endforeach
                        </ul>
                        @endif

                        @if(!empty($overview['doctor']['modules']))
                        <h3 class="h6">Modules</h3>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Module</th><th>Statut</th><th>Findings</th></tr></thead>
                                <tbody>
                                    @foreach($overview['doctor']['modules'] as $mod)
                                    <tr>
                                        <td><code>{{ $mod['module'] }}</code></td>
                                        <td>{{ $mod['status'] }}</td>
                                        <td class="small">{{ count($mod['findings'] ?? []) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @else
                        <div class="alert alert-warning py-2 small mb-0">Aucun rapport Doctor. Installez l'agent ci-dessus.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card obiora-card h-100 border-danger border-opacity-25">
                <div class="card-header">Crash Analyzer — {{ $server?->name }}</div>
                <div class="card-body">
                    @php($crash = $overview['crash_analyzer']['summary'] ?? [])
                    <dl class="row small mb-3">
                        <dt class="col-sm-5">Métriques (période)</dt>
                        <dd class="col-sm-7">{{ $crash['metrics_count'] ?? 0 }}</dd>
                        <dt class="col-sm-5">Événements critiques</dt>
                        <dd class="col-sm-7"><span class="badge text-bg-danger">{{ $crash['critical_events'] ?? 0 }}</span></dd>
                        <dt class="col-sm-5">CPU max</dt>
                        <dd class="col-sm-7">{{ $crash['cpu_max'] ?? '—' }}%</dd>
                        <dt class="col-sm-5">RAM max</dt>
                        <dd class="col-sm-7">{{ $crash['memory_max'] ?? '—' }}%</dd>
                    </dl>

                    @if(!empty($overview['crash_analyzer']['events']))
                    <h3 class="h6">Derniers événements</h3>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Date</th><th>Type</th><th>Titre</th></tr></thead>
                            <tbody>
                                @foreach(array_slice($overview['crash_analyzer']['events'], 0, 8) as $event)
                                <tr>
                                    <td class="small text-nowrap">{{ $event['detected_at'] ?? '' }}</td>
                                    <td><code class="small">{{ $event['event_type'] }}</code></td>
                                    <td class="small">{{ Str::limit($event['title'] ?? '', 40) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if(!empty($overview['crash_analyzer']['reports']))
                    <h3 class="h6">Rapports post-crash</h3>
                    <ul class="small mb-0">
                        @foreach($overview['crash_analyzer']['reports'] as $rpt)
                        <li>{{ $rpt['trigger_type'] ?? '—' }} — {{ $rpt['generated_at'] ?? '' }}</li>
                        @endforeach
                    </ul>
                    @else
                        <p class="small text-muted mb-0">Aucun rapport crash pour ce serveur.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
