<div @if($deployRunning) wire:poll.2s="pollDeploy" @endif
     x-data="{ scrollConsole() { const el = document.getElementById('obiora-deploy-console'); if (el) el.scrollTop = el.scrollHeight; } }"
     x-on:deploy-console-scroll.window="scrollConsole()"
     x-init="$watch('$wire.deployConsole', () => scrollConsole())">
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h3 mb-1">ObiOra Doctor & Suite</h1>
            <p class="text-muted mb-0">Installez Doctor et Crash Analyzer sur vos serveurs dédiés et/ou VPS, puis consultez les diagnostics depuis le panel.</p>
        </div>
        @if($server)
        <div class="d-flex gap-2">
            <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary btn-sm">Monitoring</a>
            <a href="{{ route('crash-analyzer.index', ['server' => $server->id]) }}" class="btn btn-outline-danger btn-sm">Crash Analyzer</a>
        </div>
        @endif
    </div>

    <div class="alert alert-info py-3 mb-4">
        <p class="fw-semibold mb-2">Comment installer les agents sur un serveur dédié et/ou VPS ?</p>
        <ol class="small mb-0 ps-3">
            <li class="mb-1">Choisissez le <strong>serveur panel</strong> (ci-dessous) — c'est l'entrée qui recevra les rapports.</li>
            <li class="mb-1">Renseignez l'<strong>IP</strong>, le <strong>port</strong>, l'<strong>utilisateur</strong> et le <strong>mot de passe SSH</strong> du serveur distant.</li>
            <li class="mb-1">Cliquez <strong>Tester la connexion</strong>.</li>
            <li>Si le test est OK, cliquez <strong>Installer sur le serveur</strong> : le panel se connecte, installe la clé API, envoie Doctor + Crash Analyzer, exécute les scripts et affiche les données ici.</li>
        </ol>
        @if($reportCount > 0)
            <p class="small text-success mb-0 mt-2">{{ $reportCount }} rapport(s) Doctor en base — dernier {{ $lastReportLabel }}</p>
        @endif
    </div>

    <div class="card obiora-card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Serveur panel (réception des données)</label>
                    <select wire:model.live="serverId" class="form-select">
                        @foreach($doctorFleet as $fleetServer)
                            <option value="{{ $fleetServer->id }}">{{ $fleetServer->name }} (ID {{ $fleetServer->id }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 small text-muted">
                    Jeton agent API : <code>{{ $server?->agent_token ? Str::limit($server->agent_token, 24).'…' : '—' }}</code>
                    <span class="d-block mt-1">Utilisé par les agents installés sur le serveur distant pour envoyer les rapports au panel.</span>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($fleetOverview))
    <div class="card obiora-card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Vue flotte</h2>
            <div class="table-responsive">
                <table class="table table-sm obiora-table align-middle mb-0">
                    <thead class="obiora-table-head">
                        <tr>
                            <th>Serveur</th>
                            <th>Doctor</th>
                            <th>Dernière métrique</th>
                            <th>Critiques 24h</th>
                            <th>Rapports crash</th>
                            <th>Agents installés</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fleetOverview as $row)
                        <tr @class(['obiora-fleet-selected' => $row['id'] === $server?->id])>
                            <td>
                                <span class="fw-medium">{{ $row['name'] }}</span>
                                <span class="small text-muted d-block">{{ $row['hostname'] }}</span>
                            </td>
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
                            <td>{{ $row['crash_reports'] ?: '—' }}</td>
                            <td>
                                @if($row['deployed'])
                                    <span class="badge text-bg-success">Oui</span>
                                @else
                                    <span class="badge text-bg-secondary">Non</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @if($deployRunning || $deployFinished || count($deployConsole) > 0)
    <div class="card obiora-card mb-4 border-primary">
        <div class="card-body">
            @if($deployRunning)
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-medium">Installation en cours sur le serveur distant…</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted">{{ $deployProgress }}%</span>
                    @if($canManageServers)
                    <button type="button" wire:click="cancelDeploy" wire:loading.attr="disabled" class="btn btn-outline-danger btn-sm py-0 px-2">
                        Annuler
                    </button>
                    @endif
                </div>
            </div>
            <div class="obiora-progress info mb-2">
                <div class="bar" style="width: {{ max(3, $deployProgress) }}%"></div>
            </div>
            <p class="small text-muted mb-3">{{ $deployProgressMessage ?: 'Connexion SSH, envoi des fichiers et démarrage des services…' }}</p>
            @elseif($deployFinished)
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small fw-medium {{ $deploySuccess ? 'text-success' : 'text-danger' }}">
                    {{ $deploySuccess ? 'Installation terminée' : 'Installation échouée' }}
                </span>
                <button type="button" wire:click="$set('deployFinished', false)" class="btn btn-outline-secondary btn-sm py-0 px-2">
                    Masquer
                </button>
            </div>
            @endif

            <div class="obiora-deploy-console-wrap">
                <div class="obiora-deploy-console-header">
                    <span class="small fw-medium">Console d'installation (serveur distant)</span>
                    @if($deployRunning)
                    <span class="badge text-bg-info">Live</span>
                    @endif
                </div>
                <pre id="obiora-deploy-console" class="obiora-deploy-console mb-0">@foreach($deployConsole as $line){{ $line }}
@endforeach</pre>
            </div>

            @if(count($panelDeployLogs) > 0)
            <details class="mt-3" open>
                <summary class="small fw-medium">Journal panel (persistant)</summary>
                <pre class="small bg-dark text-light p-2 rounded mt-2 mb-0" style="max-height:220px;overflow:auto">@foreach($panelDeployLogs->reverse() as $logEntry)[{{ $logEntry->created_at->format('d/m H:i:s') }}] {{ strtoupper($logEntry->level) }} — {{ $logEntry->message }}
@endforeach</pre>
                <p class="small text-muted mt-1 mb-0">Fichier serveur : <code>storage/logs/deploy.log</code></p>
            </details>
            @endif
        </div>
    </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card obiora-card h-100 border-primary">
                <div class="card-header bg-primary bg-opacity-10 fw-medium">Installer sur un serveur dédié et/ou VPS</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">Adresse IP ou hostname</label>
                            <input type="text" wire:model="sshHost" class="form-control" placeholder="Ex. 54.37.103.239">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Port SSH</label>
                            <input type="number" wire:model="sshPort" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Utilisateur</label>
                            <input type="text" wire:model="sshUser" class="form-control" placeholder="root">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Mot de passe SSH</label>
                            <input type="password" wire:model="sshPassword" class="form-control" autocomplete="off" placeholder="Utilisé pour la connexion et la 1ère installation">
                            @error('sshPassword') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="small fw-medium d-block mb-2">Composants à installer</span>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" wire:model="deployDoctor" id="deployDoctor">
                            <label class="form-check-label small" for="deployDoctor">ObiOra Doctor</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" wire:model="deployCrashAnalyzer" id="deployCrash">
                            <label class="form-check-label small" for="deployCrash">Crash Analyzer</label>
                        </div>
                    </div>

                    @if($canManageServers)
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" wire:click="testSshConnection" wire:loading.attr="disabled" class="btn btn-outline-info">
                            <span wire:loading.remove wire:target="testSshConnection">Tester la connexion</span>
                            <span wire:loading wire:target="testSshConnection">Test en cours…</span>
                        </button>
                        <button type="button" wire:click="deployRemote" wire:loading.attr="disabled" class="btn btn-primary"
                                @if($deployRunning || !$sshTestOk) disabled @endif>
                            <span wire:loading.remove wire:target="deployRemote">Installer sur le serveur</span>
                            <span wire:loading wire:target="deployRemote">Installation…</span>
                        </button>
                    </div>

                    @if(!$sshTestOk)
                        <p class="small text-muted mb-0">Le bouton d'installation s'active après un test de connexion réussi.</p>
                    @elseif($sshKeyInstalled)
                        <p class="small text-success mb-0">Connexion OK — clé SSH déjà installée sur ce serveur. L'installation va utiliser la clé dédiée du panel.</p>
                    @else
                        <p class="small text-success mb-0">Connexion OK — le panel va installer sa clé SSH, déployer les agents et récupérer les données automatiquement.</p>
                    @endif
                    @else
                    <div class="alert alert-warning py-2 small mb-0">Permission <code>servers.manage</code> requise pour installer sur un serveur distant.</div>
                    @endif

                    @if($sshTestResult)
                    <div class="alert py-2 small mt-3 mb-0 {{ $sshTestOk ? 'alert-success' : 'alert-danger' }}">
                        {{ $sshTestResult }}
                    </div>
                    @endif

                    @if($sshBootstrapResult)
                    <div class="alert alert-info py-2 small mt-3 mb-0">{{ $sshBootstrapResult }}</div>
                    @endif

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

                    @if($sshPublicKey && $canManageServers)
                    <details class="mt-4 small">
                        <summary class="text-muted" style="cursor:pointer">Détails techniques (clé SSH)</summary>
                        <div class="mt-2 obiora-copy-block">
                            <pre class="small mb-0 obiora-copy-text text-break">{{ $sshPublicKey }}</pre>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="obioraCopyFromButton(this)">Copier la clé publique</button>
                        </div>
                        @if($sshKeyInstalled)
                            <span class="badge text-bg-success mt-2">Clé installée sur le serveur</span>
                        @endif
                    </details>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card obiora-card h-100">
                <div class="card-header">Installation manuelle (SSH)</div>
                <div class="card-body">
                    <p class="small text-muted">Alternative : copiez cette commande et exécutez-la directement sur le serveur distant.</p>
                    <h3 class="h6">Doctor + Crash Analyzer</h3>
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
                            <table class="table table-sm obiora-table mb-0">
                                <thead class="obiora-table-head"><tr><th>Module</th><th>Statut</th><th>Findings</th></tr></thead>
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
                        <div class="alert alert-warning py-2 small mb-0">Aucun rapport Doctor pour ce serveur. Installez les agents ci-dessus.</div>
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
                        <table class="table table-sm obiora-table mb-0">
                            <thead class="obiora-table-head"><tr><th>Date</th><th>Type</th><th>Titre</th></tr></thead>
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
