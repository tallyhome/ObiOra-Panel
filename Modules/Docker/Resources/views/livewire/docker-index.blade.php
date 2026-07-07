<div @if($installingDocker || $uninstallingDocker) wire:poll.2s="pollDockerInstall" @endif>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Docker</h1>
            <p class="text-muted mb-0">Serveur : <strong>{{ $serverName }}</strong></p>
        </div>
        <button wire:click="refresh" class="btn btn-outline-primary btn-sm" wire:loading.attr="disabled">Actualiser</button>
        @if ($dockerInfo['installed'] ?? false)
            <button type="button" class="btn btn-outline-danger btn-sm" wire:loading.attr="disabled"
                onclick="obioraConfirmWire(this, 'uninstallDocker', 'Désinstaller Docker', 'Désinstaller Docker ? Les conteneurs seront arrêtés. Les données dans /var/lib/docker seront conservées.')"
                @if($installingDocker || $uninstallingDocker) disabled @endif>
                Désinstaller Docker
            </button>
        @endif
    </div>

    @if ($dockerInfo['installed'] ?? false)
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card obiora-card">
                    <div class="card-body py-3">
                        <div class="small text-muted">Version</div>
                        <div class="fw-medium">{{ $dockerInfo['version'] ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card obiora-card">
                    <div class="card-body py-3">
                        <div class="small text-muted">En cours</div>
                        <div class="fw-medium">{{ $dockerInfo['running'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card obiora-card">
                    <div class="card-body py-3">
                        <div class="small text-muted">Conteneurs</div>
                        <div class="fw-medium">{{ $dockerInfo['total'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card obiora-card">
                    <div class="card-body py-3">
                        <div class="small text-muted">Images</div>
                        <div class="fw-medium">{{ $dockerInfo['images'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>
    @else
        @if($installingDocker || $uninstallingDocker)
            <div class="card obiora-card mb-4 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small text-uppercase">{{ $uninstallingDocker ? 'Désinstallation Docker' : 'Installation Docker' }}</strong>
                        <span class="badge bg-info">{{ $dockerProgress }}%</span>
                    </div>
                    <div class="obiora-progress info mb-2" style="height: 12px;">
                        <div class="bar" style="width: {{ max(2, $dockerProgress) }}%"></div>
                    </div>
                    <p class="mb-0 small text-muted d-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        {{ $dockerProgressMessage ?: 'Installation en cours…' }}
                    </p>
                </div>
            </div>
        @else
            <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    Docker n'est pas installé ou inaccessible sur ce serveur.
                    @if (!empty($dockerInfo['error']))
                        <div class="small mt-1">{{ $dockerInfo['error'] }}</div>
                    @endif
                </div>
                <button type="button" class="btn btn-primary btn-sm" wire:click="installDocker" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="installDocker">Installer Docker</span>
                    <span wire:loading wire:target="installDocker">Lancement…</span>
                </button>
            </div>
        @endif
    @endif

    <div class="card obiora-card mb-3">
        <div class="card-header py-2 fw-medium">Lancer un conteneur</div>
        <div class="card-body">
            <form wire:submit="runContainer" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Image</label>
                    <input wire:model="run_image" type="text" class="form-control form-control-sm font-monospace" placeholder="nginx:alpine" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Nom <span class="text-muted">(opt.)</span></label>
                    <input wire:model="run_name" type="text" class="form-control form-control-sm font-monospace" placeholder="mon-nginx">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Ports <span class="text-muted">(opt.)</span></label>
                    <input wire:model="run_ports" type="text" class="form-control form-control-sm font-monospace" placeholder="8080:80">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100" wire:loading.attr="disabled">Run</button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs obiora-nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'containers' ? 'active' : '' }}" wire:click="$set('activeTab', 'containers')">
                Conteneurs
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'images' ? 'active' : '' }}" wire:click="$set('activeTab', 'images')">
                Images
            </button>
        </li>
    </ul>

    @if ($activeTab === 'containers')
        <div class="card obiora-card mb-3">
            <div class="card-body py-2">
                <input wire:model.live.debounce.300ms="search" type="search" class="form-control form-control-sm" placeholder="Rechercher un conteneur...">
            </div>
        </div>

        <div class="card obiora-card">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Image</th>
                            <th>Statut</th>
                            <th>Ports</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filteredContainers as $container)
                            <tr>
                                <td class="font-monospace small">{{ $container['name'] }}</td>
                                <td class="small">{{ $container['image'] }}</td>
                                <td>
                                    @php
                                        $running = str_starts_with(strtolower($container['status']), 'up');
                                    @endphp
                                    <span class="badge text-bg-{{ $running ? 'success' : 'secondary' }}">{{ \Illuminate\Support\Str::limit($container['status'], 30) }}</span>
                                </td>
                                <td class="small text-muted">{{ $container['ports'] ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <button wire:click="containerAction('{{ $container['name'] }}', 'start')" class="btn btn-outline-success btn-sm py-0">Start</button>
                                    <button wire:click="containerAction('{{ $container['name'] }}', 'stop')" class="btn btn-outline-warning btn-sm py-0">Stop</button>
                                    <button wire:click="containerAction('{{ $container['name'] }}', 'restart')" class="btn btn-outline-primary btn-sm py-0">Restart</button>
                                    <button wire:click="showLogs('{{ $container['name'] }}')" class="btn btn-outline-secondary btn-sm py-0">Logs</button>
                                    <button type="button" wire:loading.attr="disabled"
                                        onclick="obioraConfirmWire(this, 'containerAction', 'Supprimer le conteneur', 'Supprimer ce conteneur Docker ?', @js($container['name']), 'remove')"
                                        class="btn btn-outline-danger btn-sm py-0">Remove</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Aucun conteneur.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card obiora-card">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Repository</th>
                            <th>Tag</th>
                            <th>Taille</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($images as $image)
                            <tr>
                                <td class="font-monospace small">{{ $image['repository'] }}</td>
                                <td><span class="badge text-bg-light text-dark">{{ $image['tag'] }}</span></td>
                                <td class="small text-muted">{{ $image['size'] }}</td>
                                <td class="text-end">
                                    <button type="button" wire:loading.attr="disabled"
                                        onclick="obioraConfirmWire(this, 'removeImage', 'Supprimer l\'image', 'Supprimer cette image Docker ?', @js($image['id']))"
                                        class="btn btn-outline-danger btn-sm py-0">Supprimer</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Aucune image.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($logContainer)
        <div class="card obiora-card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="small fw-medium">Logs — {{ $logContainer }}</span>
                <button type="button" class="btn-close btn-sm" wire:click="$set('logContainer', null)"></button>
            </div>
            <div class="card-body p-0">
                <pre class="small mb-0 p-3 bg-dark text-light" style="max-height: 320px; overflow: auto;">{{ $logOutput ?: 'Aucun log.' }}</pre>
            </div>
        </div>
    @endif
</div>
