<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Serveurs</h1>
            <p class="text-muted small mb-0">Machines Linux surveillées par l'agent ObiOra.</p>
        </div>
        @if($canManageServers)
        <button type="button" wire:click="openAddModal" class="btn btn-primary btn-sm">+ Ajouter un serveur</button>
        @endif
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-sm obiora-table align-middle mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Nom</th>
                        <th>Statut</th>
                        <th>OS</th>
                        <th>Disque SMART</th>
                        <th>Dernière vue</th>
                        <th>Clé agent</th>
                        @if($canManageServers)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($servers as $server)
                    <tr>
                        <td>
                            <span class="fw-medium">{{ $server['name'] }}</span>
                            @if($server['is_master'])
                                <span class="badge text-bg-primary ms-1">Panel</span>
                            @endif
                            @if(!empty($server['tags']))
                                <div class="small mt-1">
                                    @foreach($server['tags'] as $tag)
                                        <span class="badge text-bg-secondary">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($server['online'] && ($server['status'] ?? '') === 'online')
                                <span class="badge text-bg-success">Online</span>
                            @elseif(($server['status'] ?? '') === 'degraded')
                                <span class="badge text-bg-warning">Degraded</span>
                            @else
                                <span class="badge text-bg-secondary">{{ $server['status'] }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $server['os_label'] ?: '—' }}</td>
                        <td>
                            @php $dh = $server['disk_health']; @endphp
                            <span @class([
                                'badge',
                                'text-bg-success' => $dh === 'Passed',
                                'text-bg-warning' => $dh === 'Warning',
                                'text-bg-danger' => $dh === 'Failed',
                                'text-bg-secondary' => $dh === 'N/A',
                            ])>{{ $dh }}</span>
                        </td>
                        <td class="small text-nowrap">
                            {{ $server['last_seen'] ?: '—' }}
                            @if($server['last_seen_human'])
                                <span class="text-muted d-block">{{ $server['last_seen_human'] }}</span>
                            @endif
                        </td>
                        <td>
                            <code class="small">{{ $server['agent_token_masked'] }}</code>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1"
                                    onclick="navigator.clipboard.writeText(@js($server['agent_token'])); window.dispatchEvent(new CustomEvent('notify', {detail:{type:'success',message:'Clé copiée'}}))">
                                Copier
                            </button>
                        </td>
                        @if($canManageServers)
                        <td class="text-nowrap">
                            <a href="{{ route('monitoring.servers.metrics', $server['id']) }}" class="btn btn-outline-primary btn-sm py-0">Métriques</a>
                            <button type="button" wire:click="showInstallFor({{ $server['id'] }})" class="btn btn-outline-info btn-sm py-0">Installer</button>
                            @if(!$server['is_master'])
                            <button type="button" wire:click="deleteServer({{ $server['id'] }})" wire:confirm="Supprimer ce serveur du panel ?"
                                    class="btn btn-outline-danger btn-sm py-0">Supprimer</button>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>

    @if($showAddModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5">Ajouter un serveur</h2>
                    <button type="button" class="btn-close" wire:click="$set('showAddModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nom du serveur *</label>
                        <input type="text" wire:model="newName" class="form-control" placeholder="Ex. Datacenter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Adresse IP (optionnel)</label>
                        <input type="text" wire:model="newIp" class="form-control" placeholder="Rempli par l'agent si vide">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-medium">Tags</label>
                        <input type="text" wire:model="newTagsInput" class="form-control" placeholder="production, seedbox">
                        <p class="form-text small mb-0">Séparez par des virgules.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('showAddModal', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="createServer" wire:loading.attr="disabled">Ajouter</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($showInstallModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);"
         @if($deployRunning) wire:poll.2s="pollDeploy" @endif>
        <div class="modal-dialog modal-lg">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5">Installer l'agent métriques</h2>
                    <button type="button" class="btn-close" wire:click="closeInstallModal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <button type="button" @class(['nav-link', 'active' => $installMode === 'ssh'])
                                    wire:click="$set('installMode', 'ssh')">Installation automatique (SSH)</button>
                        </li>
                        <li class="nav-item">
                            <button type="button" @class(['nav-link', 'active' => $installMode === 'manual'])
                                    wire:click="$set('installMode', 'manual')">Commande manuelle</button>
                        </li>
                    </ul>

                    @if($installMode === 'ssh')
                    <p class="small text-muted">Comme Doctor &amp; Suite : saisissez les accès SSH, testez la connexion, puis lancez l'installation distante (connexion sortante HTTPS uniquement côté agent).</p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Adresse IP / hôte *</label>
                            <input type="text" wire:model="sshHost" class="form-control" placeholder="203.0.113.10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Port SSH</label>
                            <input type="number" wire:model="sshPort" class="form-control" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Utilisateur</label>
                            <input type="text" wire:model="sshUser" class="form-control" placeholder="root">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Mot de passe SSH</label>
                            <input type="password" wire:model="sshPassword" class="form-control" autocomplete="off"
                                   placeholder="Requis pour la 1ère connexion (clé dédiée ensuite)">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="testSshConnection" wire:loading.attr="disabled">
                            Tester la connexion
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="deployMonitorAgent" wire:loading.attr="disabled"
                                @if($deployRunning || !$sshTestOk) disabled @endif>
                            Installer automatiquement
                        </button>
                    </div>
                    @if($sshTestResult)
                        <div @class(['alert py-2 small', 'alert-success' => $sshTestOk, 'alert-danger' => !$sshTestOk])>{{ $sshTestResult }}</div>
                    @endif
                    @if($deployRunning || $deployFinished)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $deployProgressMessage }}</span>
                            <span>{{ $deployProgress }}%</span>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar" style="width: {{ $deployProgress }}%"></div>
                        </div>
                    </div>
                    @endif
                    @if($deployError)
                        <div class="alert alert-danger py-2 small">{{ $deployError }}</div>
                    @endif
                    @if(count($deployConsole) > 0)
                    <div class="obiora-deploy-console small font-monospace border rounded p-2 mb-0" style="max-height:180px;overflow:auto;">
                        @foreach($deployConsole as $line)
                            <div>{{ $line }}</div>
                        @endforeach
                    </div>
                    @endif
                    @else
                    <p class="small">Exécutez cette commande sur le serveur (root ou sudo). L'agent envoie les métriques chaque minute — en ligne sous 1–2 minutes.</p>
                    <div class="obiora-copy-block">
                        <pre class="small mb-0 obiora-copy-text text-break">{{ $installCommand }}</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2"
                                onclick="obioraCopyFromButton(this.closest('.obiora-copy-block').querySelector('.obiora-copy-text'))">Copier</button>
                    </div>
                    <div class="alert alert-info py-2 small mt-3 mb-0">
                        Connexion sortante HTTPS uniquement — aucun port entrant requis sur le serveur distant.
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" wire:click="closeInstallModal">Terminé</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($showRemovedModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5 text-danger">Serveur retiré</h2>
                    <button type="button" class="btn-close" wire:click="$set('showRemovedModal', false)"></button>
                </div>
                <div class="modal-body">
                    <p class="small"><strong>{{ $removedServerName }}</strong> a été retiré du panel. Si l'agent est encore installé sur la machine, exécutez :</p>
                    <div class="obiora-copy-block">
                        <pre class="small mb-0 obiora-copy-text">{{ $uninstallCommand }}</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2"
                                onclick="obioraCopyFromButton(this.closest('.obiora-copy-block').querySelector('.obiora-copy-text'))">Copier</button>
                    </div>
                    <div class="alert alert-warning py-2 small mt-3 mb-0">
                        Arrête le service agent et retire l'unité systemd. Les données historiques du panel sont conservées jusqu'à purge manuelle.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" wire:click="$set('showRemovedModal', false)">Terminé</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
