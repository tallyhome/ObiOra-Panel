<div @if($deployRunning) wire:poll.2s="pollDeploy" @endif
     x-data="{ scrollConsole() { const el = document.getElementById('obiora-deploy-console'); if (el) el.scrollTop = el.scrollHeight; } }"
     x-on:deploy-console-scroll.window="scrollConsole()"
     x-init="$watch('$wire.deployConsole', () => scrollConsole())">
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h3 mb-1">ObiOra Doctor & Suite</h1>
            <p class="text-muted mb-0">Installez Doctor, Crash Analyzer et CrashHunter sur vos serveurs dédiés et/ou VPS, puis consultez les diagnostics depuis le panel.</p>
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
            <li>Si le test est OK, cliquez <strong>Installer sur le serveur</strong> : le panel se connecte, installe la clé API, envoie Doctor + Crash Analyzer + CrashHunter, exécute les scripts et affiche les données ici.</li>
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
                            <option value="{{ $fleetServer->id }}">{{ $fleetServer->name }} — {{ $fleetServer->ip_address }} (ID {{ $fleetServer->id }})</option>
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

    @if($server)
    <div class="card obiora-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Date et fuseau horaire — {{ $server->name }}</span>
            @if($timezoneLoading)
                <span class="badge text-bg-secondary">Lecture…</span>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <dl class="row small mb-0">
                        <dt class="col-sm-5">Heure serveur</dt>
                        <dd class="col-sm-7">
                            @if($serverDateTime)
                                <strong>{{ $serverDateTime }}</strong>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>
                        <dt class="col-sm-5">Fuseau actuel</dt>
                        <dd class="col-sm-7">
                            @if($serverTimezone)
                                <code>{{ $serverTimezone }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>
                        @if($serverNtp !== null)
                        <dt class="col-sm-5">Synchronisation NTP</dt>
                        <dd class="col-sm-7">{{ $serverNtp === 'yes' ? 'Active' : ($serverNtp === 'no' ? 'Inactive' : $serverNtp) }}</dd>
                        @endif
                    </dl>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium" for="doctorTimezoneSelect">Nouveau fuseau horaire</label>
                    <select id="doctorTimezoneSelect" wire:model="selectedTimezone" class="form-select" @if(!$canManageServers) disabled @endif>
                        @foreach($timezoneChoices as $tzValue => $tzLabel)
                            <option value="{{ $tzValue }}">{{ $tzLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    @if($canManageServers)
                    <button type="button"
                            wire:click="applyServerTimezone"
                            wire:loading.attr="disabled"
                            wire:target="applyServerTimezone,refreshServerTimezone"
                            class="btn btn-outline-primary w-100">
                        <span wire:loading.remove wire:target="applyServerTimezone">Appliquer au serveur</span>
                        <span wire:loading wire:target="applyServerTimezone">Mise à jour…</span>
                    </button>
                    <button type="button"
                            wire:click="refreshServerTimezone"
                            wire:loading.attr="disabled"
                            wire:target="refreshServerTimezone,applyServerTimezone"
                            class="btn btn-link btn-sm px-0 mt-1">
                        Actualiser l'heure
                    </button>
                    @else
                    <p class="small text-muted mb-0">Permission <code>servers.manage</code> requise pour modifier le fuseau.</p>
                    @endif
                </div>
            </div>
            <p class="small text-muted mb-0 mt-3">
                Le panel applique <code>timedatectl set-timezone</code> et active la synchronisation NTP pour corriger la date et l'heure du serveur sélectionné.
            </p>
            @if($timezoneMessage)
            <div class="alert py-2 small mt-3 mb-0 alert-info">{{ $timezoneMessage }}</div>
            @endif
        </div>
    </div>
    @endif

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
                                <span class="small text-muted d-block">{{ $row['display_ip'] ?? $row['hostname'] }}</span>
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
                                <div class="d-flex flex-wrap gap-1">
                                    @if($row['agents_slave'] ?? false)
                                        <span class="badge text-bg-primary" title="Agent seedbox">Seedbox</span>
                                    @endif
                                    @if($row['agents_doctor'] ?? false)
                                        <span class="badge text-bg-success" title="ObiOra Doctor">Doctor</span>
                                    @endif
                                    @if($row['agents_crash'] ?? false)
                                        <span class="badge text-bg-danger" title="Crash Analyzer">Crash</span>
                                    @endif
                                    @if($row['agents_crash_hunter'] ?? false)
                                        <span class="badge text-bg-warning text-dark" title="CrashHunter Enterprise">Hunter</span>
                                    @endif
                                    @if(!($row['deployed'] ?? false))
                                        <span class="badge text-bg-secondary">Aucun</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @if($deployRunning || ($deployFinished && !$deployDismissed))
    <div class="card obiora-card mb-4 border-primary obiora-deploy-panel">
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
                <button type="button" wire:click="dismissDeployResult" class="btn btn-outline-secondary btn-sm py-0 px-2">
                    Fermer
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
            <details class="mt-3">
                <summary class="small fw-medium">Journal panel (persistant)</summary>
                <pre class="small bg-dark text-light p-2 rounded mt-2 mb-0 obiora-deploy-journal">@foreach($panelDeployLogs->reverse() as $logEntry)[{{ $logEntry->created_at->format('d/m H:i:s') }}] {{ strtoupper($logEntry->level) }} — {{ $logEntry->message }}
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
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" wire:model="deployCrashHunter" id="deployCrashHunter">
                            <label class="form-check-label small" for="deployCrashHunter">CrashHunter Enterprise</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" wire:model="deploySlave" id="deploySlave">
                            <label class="form-check-label small" for="deploySlave">Agent seedbox (slave)</label>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Décochez l'agent seedbox sur un dédié Virtualizor ou tout serveur qui n'héberge pas de seedbox.</p>
                    </div>

                    @if($canManageServers)
                    <div class="border rounded p-3 mb-3 bg-light">
                        <span class="small fw-medium d-block mb-2">Contrôle des agents distants</span>
                        <p class="small text-muted mb-2">Une fois le problème identifié et résolu, arrêtez les agents diagnostics ou supprimez-les entièrement (services, logs, snapshots, répertoires).</p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" wire:click="refreshRunningAgents" wire:loading.attr="disabled" class="btn btn-outline-secondary btn-sm"
                                    @if(!$sshTestOk) disabled @endif>
                                <span wire:loading.remove wire:target="refreshRunningAgents">Voir les agents actifs</span>
                                <span wire:loading wire:target="refreshRunningAgents">Lecture…</span>
                            </button>
                            <button type="button" wire:click="stopAllAgents" wire:loading.attr="disabled" class="btn btn-outline-danger btn-sm"
                                    @if(!$sshTestOk) disabled @endif
                                    onclick="return confirm('Arrêter et désactiver tous les agents diagnostics (CrashHunter, Crash Analyzer, Doctor, seedbox) ?')">
                                <span wire:loading.remove wire:target="stopAllAgents">Arrêter tous les agents</span>
                                <span wire:loading wire:target="stopAllAgents">Arrêt…</span>
                            </button>
                            <button type="button" wire:click="purgeAllAgents" wire:loading.attr="disabled" class="btn btn-danger btn-sm"
                                    @if(!$sshTestOk) disabled @endif
                                    onclick="return confirm('Supprimer définitivement tous les agents ObiOra Suite (services, fichiers, logs, snapshots) ? Cette action est irréversible.')">
                                <span wire:loading.remove wire:target="purgeAllAgents">Supprimer agents et fichiers</span>
                                <span wire:loading wire:target="purgeAllAgents">Suppression…</span>
                            </button>
                        </div>
                        @if($agentControlMessage)
                            <div class="alert py-2 small mb-2 {{ $agentControlOk ? 'alert-success' : 'alert-warning' }}">{{ $agentControlMessage }}</div>
                        @endif
                        @if(!empty($runningAgents))
                        <div class="table-responsive">
                            <table class="table table-sm obiora-table mb-0">
                                <thead class="obiora-table-head"><tr><th>Service</th><th>État</th><th>Description</th></tr></thead>
                                <tbody>
                                    @foreach($runningAgents as $svc)
                                    <tr>
                                        <td><code class="small">{{ $svc['unit'] ?? '' }}</code></td>
                                        <td>
                                            @if($svc['running'] ?? false)
                                                <span class="badge text-bg-success">actif</span>
                                            @else
                                                <span class="badge text-bg-secondary">{{ $svc['active'] ?? '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ Str::limit($svc['description'] ?? '', 50) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
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
                        <pre class="small bg-dark text-light p-2 rounded mt-1 mb-0 obiora-deploy-steps-pre">{{ $step['output'] }}</pre>
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
    @php($plainSummary = $overview['plain_summary'] ?? null)
    @if($plainSummary)
    <div class="card obiora-card mb-4 border-{{ $plainSummary['severity'] === 'critical' ? 'danger' : ($plainSummary['severity'] === 'warning' ? 'warning' : 'success') }} border-opacity-50">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>Ce qui s'est passé — synthèse</span>
            <span class="badge {{ $plainSummary['severity'] === 'critical' ? 'text-bg-danger' : ($plainSummary['severity'] === 'warning' ? 'text-bg-warning' : 'text-bg-success') }}">
                {{ $plainSummary['severity'] === 'critical' ? 'Problème détecté' : ($plainSummary['severity'] === 'warning' ? 'À surveiller' : 'RAS') }}
            </span>
        </div>
        <div class="card-body">
            <h2 class="h5 mb-1">{{ $plainSummary['headline'] ?? '—' }}</h2>
            @if(!empty($plainSummary['subtitle']))
                <p class="text-muted small mb-3">{{ $plainSummary['subtitle'] }}</p>
            @endif

            @if(!empty($plainSummary['items']))
            <div class="vstack gap-3">
                @foreach($plainSummary['items'] as $item)
                <div class="border rounded p-3 bg-light">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge text-bg-secondary">{{ $item['source'] ?? '' }}</span>
                        @if(($item['kind'] ?? '') === 'freeze')
                            <span class="badge text-bg-warning">Freeze</span>
                        @elseif(($item['kind'] ?? '') === 'crash')
                            <span class="badge text-bg-danger">Crash / reboot</span>
                        @endif
                        @if(($item['severity'] ?? '') === 'critical')
                            <span class="badge text-bg-danger">Critique</span>
                        @endif
                    </div>
                    <p class="fw-medium mb-1">{{ $item['title'] ?? '' }}</p>
                    @if(!empty($item['explanation']))
                        <p class="small text-muted mb-2">{{ Str::limit($item['explanation'], 400) }}</p>
                    @endif
                    @if(!empty($item['actions']))
                        <p class="small fw-medium mb-1">Que faire :</p>
                        <ul class="small mb-0">
                            @foreach($item['actions'] as $action)
                            <li>{{ $action }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @endforeach
            </div>
            @else
                <p class="small text-muted mb-0">Les trois agents envoient leurs métriques au panel. Installez-les sur le serveur à diagnostiquer pour alimenter cette synthèse.</p>
            @endif
        </div>
    </div>
    @endif

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
                            <li class="mb-2">
                                <strong>{{ $finding['module'] ?? '' }}</strong> — {{ $finding['title'] ?? '' }}
                                @if(!empty($finding['recommendation']))
                                    <br><span class="text-muted">→ {{ $finding['recommendation'] }}</span>
                                @endif
                            </li>
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
                    @php($journalBoot = $overview['crash_analyzer']['journal_boot'] ?? null)
                    @php($hardware = $overview['crash_analyzer']['hardware'] ?? null)
                    @php($tools = $overview['crash_analyzer']['tools'] ?? null)
                    <dl class="row small mb-3">
                        <dt class="col-sm-5">Métriques (période)</dt>
                        <dd class="col-sm-7">{{ $crash['metrics_count'] ?? 0 }}</dd>
                        <dt class="col-sm-5">Événements critiques</dt>
                        <dd class="col-sm-7"><span class="badge text-bg-danger">{{ $crash['critical_events'] ?? 0 }}</span></dd>
                        <dt class="col-sm-5">CPU max</dt>
                        <dd class="col-sm-7">{{ $crash['cpu_max'] ?? '—' }}%</dd>
                        <dt class="col-sm-5">RAM max</dt>
                        <dd class="col-sm-7">{{ $crash['memory_max'] ?? '—' }}%</dd>
                        @if($journalBoot)
                        <dt class="col-sm-5">Journal persistant</dt>
                        <dd class="col-sm-7">{{ ($journalBoot['persistent_journal'] ?? false) ? '✔ actif' : '✗ inactif' }}</dd>
                        <dt class="col-sm-5">Boots journalctl</dt>
                        <dd class="col-sm-7">{{ $journalBoot['boots_count'] ?? '—' }}</dd>
                        @endif
                    </dl>

                    @if($journalBoot && !empty($journalBoot['boots']))
                    <h3 class="h6">journalctl --list-boots</h3>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm obiora-table mb-0">
                            <thead class="obiora-table-head"><tr><th>Idx</th><th>ID</th><th>Date</th></tr></thead>
                            <tbody>
                                @foreach(array_slice($journalBoot['boots'], -6) as $boot)
                                <tr>
                                    <td><code>{{ $boot['index'] ?? '' }}</code></td>
                                    <td class="small text-muted">{{ Str::limit($boot['id'] ?? '', 12) }}</td>
                                    <td class="small">{{ $boot['date'] ?? '' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if($journalBoot && !empty($journalBoot['previous_boot_errors']))
                    <h3 class="h6">Boot précédent — erreurs (journalctl -b -1)</h3>
                    <pre class="small bg-dark text-light p-2 rounded mb-3" style="max-height:160px;overflow:auto">{{ Str::limit($journalBoot['previous_boot_errors'], 2000) }}</pre>
                    @endif

                    @if($hardware && ($hardware['available'] ?? false))
                    <h3 class="h6">Inventaire matériel</h3>
                    <details class="small mb-3">
                        <summary>dmidecode / lscpu / lspci</summary>
                        <pre class="small mt-2 mb-0" style="max-height:200px;overflow:auto">{{ Str::limit(($hardware['lscpu'] ?? '')."\n".($hardware['lspci_network_storage'] ?? ''), 2500) }}</pre>
                    </details>
                    @endif

                    @if($tools)
                    <p class="small text-muted mb-3">
                        Outils :
                        @foreach(['strace','time','dmidecode','lscpu','lspci'] as $tool)
                            <span class="badge {{ ($tools['installed'][$tool] ?? false) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $tool }}</span>
                        @endforeach
                    </p>
                    @endif

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

    @if($overview && !empty($overview['crash_hunter']))
    @php($hunter = $overview['crash_hunter']['summary'] ?? [])
    @php($hunterCharts = $overview['crash_hunter']['charts'] ?? [])
    @php($hunterInsights = $overview['crash_hunter']['latest_report_insights'] ?? null)
    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card obiora-card border-warning border-opacity-50">
                <div class="card-header">CrashHunter Enterprise — Black Box &amp; Witness</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-2"><span class="small text-muted d-block">Witness</span>
                            @php($ws = $hunter['witness_status'] ?? 'unknown')
                            <span class="badge {{ $ws === 'alive' ? 'text-bg-success' : ($ws === 'timeout' ? 'text-bg-warning' : 'text-bg-danger') }}">{{ $ws }}</span>
                            @if(!empty($hunter['witness_gap_seconds']))
                                <span class="small text-muted d-block">gap {{ $hunter['witness_gap_seconds'] }}s</span>
                            @endif
                        </div>
                        <div class="col-md-2"><span class="small text-muted d-block">Ring buffer</span><strong>{{ $hunter['ring_count'] ?? '—' }}</strong> slots</div>
                        <div class="col-md-2"><span class="small text-muted d-block">Snapshots reçus</span><strong>{{ $hunter['snapshots_in_window'] ?? 0 }}</strong></div>
                        <div class="col-md-2"><span class="small text-muted d-block">CPU max</span><strong>{{ $hunter['cpu_max'] ?? '—' }}%</strong></div>
                        <div class="col-md-2"><span class="small text-muted d-block">Critiques 24h</span><span class="badge text-bg-danger">{{ $hunter['critical_events_24h'] ?? 0 }}</span></div>
                        <div class="col-md-2"><span class="small text-muted d-block">Mode incident</span><strong>{{ ($hunter['incident_mode'] ?? false) ? 'OUI' : 'non' }}</strong></div>
                    </div>

                    @if(!empty($hunterCharts['cpu']) || !empty($hunterCharts['load']))
                    <h3 class="h6">Métriques (dernière heure)</h3>
                    <div class="row g-3 mb-3" wire:ignore>
                        <div class="col-md-6"><div id="ch-hunter-cpu" style="min-height:200px"></div></div>
                        <div class="col-md-6"><div id="ch-hunter-load" style="min-height:200px"></div></div>
                        <div class="col-md-6"><div id="ch-hunter-iowait" style="min-height:200px"></div></div>
                        <div class="col-md-6"><div id="ch-hunter-psi" style="min-height:200px"></div></div>
                    </div>
                    <script>
                    (function () {
                        if (typeof ApexCharts === 'undefined') return;
                        const charts = @json($hunterCharts);
                        function render(id, title, series, color) {
                            const el = document.getElementById(id);
                            if (!el || !series || !series.length) return;
                            const data = series.filter(p => p.v !== null && p.v !== undefined).map(p => [p.t * 1000, p.v]);
                            if (!data.length) return;
                            new ApexCharts(el, {
                                chart: { type: 'line', height: 200, toolbar: { show: false }, animations: { enabled: false } },
                                series: [{ name: title, data }],
                                xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
                                stroke: { width: 2, curve: 'smooth' },
                                colors: [color || '#f59e0b'],
                                title: { text: title, style: { fontSize: '13px' } },
                            }).render();
                        }
                        render('ch-hunter-cpu', 'CPU %', charts.cpu || [], '#ef4444');
                        render('ch-hunter-load', 'Load 1m', charts.load || [], '#3b82f6');
                        render('ch-hunter-iowait', 'IOWait %', charts.iowait || [], '#8b5cf6');
                        render('ch-hunter-psi', 'PSI IO avg10', charts.pressure_io || [], '#f97316');
                    })();
                    </script>
                    @endif

                    @if($hunterInsights)
                    <div class="alert alert-{{ ($hunterInsights['reboot_detected'] ?? false) ? 'danger' : 'warning' }} py-3 mb-3">
                        <h3 class="h6 mb-2">Analyse post-reboot — {{ $hunterInsights['report_id'] ?? '' }}</h3>
                        <p class="small mb-2">
                            <strong>Verdict :</strong> {{ $hunterInsights['verdict'] ?? '—' }}
                            @if(!empty($hunterInsights['reboot_classification']))
                                · <strong>Type :</strong> {{ $hunterInsights['reboot_classification'] }}
                            @endif
                            @if(!empty($hunterInsights['reboot_reason']))
                                · <strong>Cause :</strong> {{ $hunterInsights['reboot_reason'] }}
                            @endif
                        </p>
                        @if(!empty($hunterInsights['causal_story']))
                        <p class="small mb-2"><strong>Chronologie :</strong> {{ Str::limit($hunterInsights['causal_story'], 500) }}</p>
                        @endif
                        @if(!empty($hunterInsights['recommendations']))
                        <h4 class="h6 mt-2">Pistes de résolution</h4>
                        <ul class="small mb-0">
                            @foreach($hunterInsights['recommendations'] as $rec)
                            <li class="mb-2">
                                @if(is_array($rec))
                                    <strong>{{ $rec['title'] ?? $rec['category'] ?? 'Recommandation' }}</strong>
                                    @if(!empty($rec['actions']) && is_array($rec['actions']))
                                        <ul class="mt-1 mb-0">
                                            @foreach($rec['actions'] as $action)
                                            <li>{{ $action }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif(!empty($rec['action']))
                                        — {{ $rec['action'] }}
                                        @if(!empty($rec['detail']))<br><span class="text-muted">{{ $rec['detail'] }}</span>@endif
                                    @endif
                                @else
                                    {{ $rec }}
                                @endif
                            </li>
                            @endforeach
                        </ul>
                        @endif
                    </div>
                    @endif

                    @if(!empty($overview['crash_hunter']['events']))
                    <h3 class="h6">Timeline événements</h3>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm obiora-table mb-0">
                            <thead class="obiora-table-head"><tr><th>Date</th><th>Sévérité</th><th>Type</th><th>Détail</th></tr></thead>
                            <tbody>
                                @foreach(array_slice($overview['crash_hunter']['events'], 0, 12) as $event)
                                <tr>
                                    <td class="small text-nowrap">{{ $event['detected_at'] ?? '' }}</td>
                                    <td><span class="badge {{ ($event['severity'] ?? '') === 'critical' ? 'text-bg-danger' : 'text-bg-secondary' }}">{{ $event['severity'] ?? '' }}</span></td>
                                    <td><code class="small">{{ $event['event_type'] ?? '' }}</code></td>
                                    <td class="small">{{ Str::limit($event['details'] ?? $event['title'] ?? '', 60) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if(!empty($overview['crash_hunter']['incidents']))
                    <h3 class="h6">Incidents (freeze silencieux)</h3>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm obiora-table mb-0">
                            <thead class="obiora-table-head"><tr><th>ID</th><th>Triggers</th><th>Snapshots</th><th>Statut</th><th>Début</th></tr></thead>
                            <tbody>
                                @foreach($overview['crash_hunter']['incidents'] as $inc)
                                <tr>
                                    <td><code class="small">{{ $inc['external_id'] ?? '' }}</code></td>
                                    <td class="small">{{ implode(', ', $inc['triggers'] ?? []) }}</td>
                                    <td>{{ $inc['snapshot_count'] ?? 0 }}</td>
                                    <td><span class="badge {{ ($inc['status'] ?? '') === 'active' ? 'text-bg-warning' : 'text-bg-secondary' }}">{{ $inc['status'] ?? 'ended' }}</span></td>
                                    <td class="small text-nowrap">{{ $inc['started_at'] ?? '' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if(!empty($overview['crash_hunter']['reports']))
                    <h3 class="h6">Rapports Black Box</h3>
                    <ul class="small mb-0">
                        @foreach($overview['crash_hunter']['reports'] as $rpt)
                        <li>{{ $rpt['external_id'] ?? '' }} — {{ $rpt['verdict'] ?? $rpt['trigger_type'] ?? '—' }} ({{ $rpt['generated_at'] ?? '' }})</li>
                        @endforeach
                    </ul>
                    @else
                    <p class="small text-muted mb-0">Aucune donnée CrashHunter reçue — cochez « CrashHunter Enterprise » et installez les agents.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
