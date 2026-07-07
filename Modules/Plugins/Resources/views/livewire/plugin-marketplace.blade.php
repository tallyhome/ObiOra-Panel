<div @if($installRunning) wire:poll.2s="pollInstall" @endif>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Marketplace</h1>
            <p class="text-muted mb-0">Applications en un clic — serveur : <strong>{{ $serverName }}</strong></p>
        </div>
    </div>

    @if (! $dockerInstalled)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <strong>Docker requis</strong> — la plupart des applications du Marketplace s'installent via Docker.
                Installez Docker avant d'ajouter une application.
            </div>
            <a href="{{ route('docker.index') }}" class="btn btn-primary btn-sm">Installer Docker</a>
        </div>
    @endif

    @if ($installRunning)
        <div class="card obiora-card mb-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-medium">
                        @if ($installingSlug)
                            Opération en cours — <code class="text-success">{{ $installingSlug }}</code>
                        @else
                            Opération en cours…
                        @endif
                    </span>
                    <span class="small text-muted">{{ $installProgress }}%</span>
                </div>
                <div class="obiora-progress info mb-2">
                    <div class="bar" style="width: {{ max(3, $installProgress) }}%"></div>
                </div>
                <p class="small text-muted mb-0">{{ $installProgressMessage ?: 'Veuillez patienter…' }}</p>
            </div>
        </div>
    @endif

    @if ($installedApps->isNotEmpty())
        <div class="card obiora-card mb-4">
            <div class="card-header py-2">Centre de contrôle — applications installées</div>
            <div class="table-responsive">
                <table class="table table-sm obiora-table mb-0">
                    <thead class="obiora-table-head sticky-top">
                        <tr>
                            <th>État</th>
                            <th>Application</th>
                            <th>Runtime</th>
                            <th>Accès</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($installedApps as $row)
                            @php
                                $app = $row['app'];
                                $runtime = $row['runtime_status'] ?? 'unknown';
                                $dotClass = match($runtime) {
                                    'running' => 'ok',
                                    'stopped' => 'danger',
                                    default => 'warning',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="obiora-status-dot {{ $dotClass }}" title="{{ $runtime }}"></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @php $installedPackage = $catalog->find($app->slug); @endphp
                                        @if ($installedPackage)
                                            @include('plugins::components.marketplace-app-icon', ['package' => $installedPackage, 'size' => 32, 'class' => 'obiora-marketplace-icon-sm'])
                                        @endif
                                        <div>
                                            <strong>{{ $row['name'] }}</strong>
                                            <div class="small text-muted">v{{ $row['version'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    {{ $row['runtime_type'] ?? 'docker' }}
                                    @if (!empty($row['container']))
                                        <div class="text-muted font-monospace">{{ $row['container'] }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if (!empty($row['url']))
                                        <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="text-success">{{ $row['url'] }}</a>
                                    @elseif (!empty($row['port']))
                                        <span class="text-muted">Port {{ $row['port'] }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <button wire:click="appAction({{ $app->id }}, 'start')" class="btn btn-outline-success btn-sm py-0" wire:loading.attr="disabled">Start</button>
                                    <button wire:click="appAction({{ $app->id }}, 'stop')" class="btn btn-outline-danger btn-sm py-0" wire:loading.attr="disabled">Stop</button>
                                    <button wire:click="appAction({{ $app->id }}, 'restart')" class="btn btn-outline-warning btn-sm py-0" wire:loading.attr="disabled">Restart</button>
                                    <button wire:click="showAppInfo({{ $app->id }})" class="btn btn-outline-primary btn-sm py-0">Infos</button>
                                    <button wire:click="showAppLogs({{ $app->id }})" class="btn btn-outline-secondary btn-sm py-0">Logs</button>
                                    <button type="button" wire:loading.attr="disabled"
                                        onclick="obioraConfirmWire(this, 'uninstall', 'Désinstaller', @js('Désinstaller '.$app->name.' ?'), {{ $app->id }})"
                                        class="btn btn-outline-danger btn-sm py-0">×</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($infoAppId)
        <div class="card obiora-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span>Informations — {{ $appInfo['name'] ?? '' }}</span>
                <button type="button" class="btn-close btn-close-white btn-sm" wire:click="closeAppInfo"></button>
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <dl class="mb-0">
                            <dt class="text-muted">État runtime</dt>
                            <dd>{{ $appInfo['runtime_status'] ?? '—' }}</dd>
                            <dt class="text-muted">Type</dt>
                            <dd>{{ $appInfo['runtime_type'] ?? '—' }}</dd>
                            <dt class="text-muted">Conteneur / service</dt>
                            <dd class="font-monospace">{{ $appInfo['container'] ?? '—' }}</dd>
                            <dt class="text-muted">Port</dt>
                            <dd>{{ $appInfo['port'] ?? '—' }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="mb-0">
                            <dt class="text-muted">URL</dt>
                            <dd>
                                @if (!empty($appInfo['url']))
                                    <a href="{{ $appInfo['url'] }}" target="_blank" rel="noopener" class="text-success">{{ $appInfo['url'] }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                            <dt class="text-muted">Installé le</dt>
                            <dd>{{ $appInfo['installed_at'] ?? '—' }}</dd>
                            @if (!empty($appInfo['username']))
                                <dt class="text-muted">Identifiant</dt>
                                <dd class="font-monospace">{{ $appInfo['username'] }}</dd>
                            @endif
                            <dt class="text-muted">Utilisation</dt>
                            <dd>{{ $appInfo['usage'] ?: 'Consultez la documentation du package.' }}</dd>
                        </dl>
                    </div>
                </div>
                @if ($appLogOutput)
                    <hr class="border-secondary">
                    <pre class="small mb-0 p-3 rounded obiora-log-pre">{{ $appLogOutput }}</pre>
                @elseif (!empty($appInfo['install_output']))
                    <hr class="border-secondary">
                    <p class="small text-muted mb-1">Sortie installation :</p>
                    <pre class="small mb-0 p-3 rounded obiora-log-pre">{{ $appInfo['install_output'] }}</pre>
                @endif
            </div>
        </div>
    @endif

    @if ($setupPackage)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.65);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content obiora-card border-secondary">
                    <div class="modal-header border-secondary">
                        <div class="d-flex align-items-center gap-3">
                            @include('plugins::components.marketplace-app-icon', ['package' => $setupPackage, 'size' => 40])
                            <h2 class="modal-title h5 mb-0">Paramètres d'installation — {{ $setupPackage->name() }}</h2>
                        </div>
                        <button type="button" class="btn-close btn-close-white" wire:click="cancelInstallSetup"></button>
                    </div>
                    @if ($setupPackage->installOptions() !== [])
                        <form id="obiora-setup-form-{{ $setupSlug }}">
                            <div class="modal-body">
                                <p class="small text-muted mb-4">
                                    Configurez l'application avant l'installation.
                                    @if ($setupPackage->databaseAutoProvision())
                                        Une base MySQL sera créée automatiquement.
                                    @endif
                                </p>

                                <div class="row g-3">
                                    @foreach ($setupPackage->installOptions() as $field)
                                        @php
                                            $name = $field['name'] ?? '';
                                            $type = $field['type'] ?? 'text';
                                            $isPassword = $type === 'password';
                                        @endphp
                                        <div class="col-12" wire:key="setup-field-{{ $setupSlug }}-{{ $name }}">
                                            <label class="form-label small mb-1" for="setup-{{ $name }}">
                                                {{ $field['label'] ?? $name }}
                                                @if ($field['required'] ?? false)
                                                    <span class="text-danger">*</span>
                                                @endif
                                            </label>
                                            <input
                                                id="setup-{{ $name }}"
                                                type="{{ $isPassword ? 'password' : 'text' }}"
                                                class="form-control obiora-input"
                                                data-setup-field="{{ $name }}"
                                                value="{{ $isPassword ? '' : ($field['default'] ?? '') }}"
                                                autocomplete="{{ $isPassword ? 'new-password' : 'off' }}"
                                            >
                                            @if (!empty($field['help']))
                                                <div class="form-text">{{ $field['help'] }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="modal-footer border-secondary">
                                <button type="button" class="btn btn-outline-secondary" wire:click="cancelInstallSetup">Annuler</button>
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    wire:loading.attr="disabled"
                                    @if($installRunning) disabled @endif
                                    onclick="obioraSubmitInstallSetup(this)"
                                >
                                    Installer maintenant
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="modal-body">
                            <p class="small text-muted mb-0">
                                @if ($setupPackage->databaseAutoProvision())
                                    L'installation va créer automatiquement une base MySQL dédiée pour {{ $setupPackage->name() }}.
                                    Les identifiants seront affichés dans les informations de l'application une fois l'installation terminée.
                                @else
                                    Confirmez l'installation de {{ $setupPackage->name() }}.
                                @endif
                            </p>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-secondary" wire:click="cancelInstallSetup">Annuler</button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                wire:click="confirmInstallSetupWithoutForm"
                                wire:loading.attr="disabled"
                                @if($installRunning) disabled @endif
                            >
                                Installer maintenant
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($installLogModalSlug)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.65);">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content obiora-card border-secondary">
                    <div class="modal-header border-secondary">
                        <h2 class="modal-title h5 mb-0">Logs — {{ $installLogModalSlug }}</h2>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeInstallLogModal"></button>
                    </div>
                    <div class="modal-body">
                        <pre class="small mb-0 p-3 rounded obiora-log-pre">{{ $installLogModalOutput }}</pre>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card obiora-card mb-3">
        <div class="card-header py-2">Catalogue — installer une application</div>
        <div class="card-body pb-2">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <input wire:model.live.debounce.300ms="search" type="search" class="form-control form-control-sm obiora-input" placeholder="Rechercher une app...">
                </div>
                <div class="col-md-3">
                    <select wire:model.live="category" class="form-select form-select-sm">
                        <option value="">Toutes les catégories</option>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="obiora-marketplace-grid">
                <div class="row g-3">
                    @forelse ($filtered as $package)
                        @php
                            $isInstalled = in_array($package->slug, $installedSlugs, true);
                            $isInstalling = $installRunning && $installingSlug === $package->slug;
                            $hasFailed = $failedInstallSlug === $package->slug;
                        @endphp
                        <div class="col-sm-6 col-lg-4 col-xl-3" wire:key="marketplace-card-{{ $package->slug }}">
                            <article class="obiora-marketplace-card h-100">
                                <div class="obiora-marketplace-card-body">
                                    @include('plugins::components.marketplace-app-icon', ['package' => $package])
                                    <div class="min-w-0">
                                        <h3 class="obiora-marketplace-title">{{ $package->name() }}</h3>
                                        <p class="obiora-marketplace-desc">{{ $package->description() }}</p>
                                    </div>
                                </div>
                                <div class="obiora-marketplace-footer">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="badge text-bg-light">{{ $categories[$package->category()] ?? $package->category() }}</span>
                                        <span class="small text-muted">v{{ $package->version() }}</span>
                                    </div>
                                    @if ($isInstalled)
                                        <span class="badge text-bg-success">Installé</span>
                                    @elseif ($isInstalling)
                                        <div class="d-flex gap-1">
                                            <span class="badge text-bg-warning">En cours…</span>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm py-0"
                                                wire:click="showInstallLogModal('{{ $package->slug }}')"
                                            >Logs</button>
                                        </div>
                                    @elseif ($hasFailed)
                                        <div class="d-flex gap-1">
                                            <button
                                                wire:click="install('{{ $package->slug }}')"
                                                class="btn btn-primary btn-sm"
                                                wire:loading.attr="disabled"
                                                @if($installRunning) disabled @endif
                                            >Réessayer</button>
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm py-0"
                                                wire:click="showInstallLogModal('{{ $package->slug }}')"
                                            >Logs</button>
                                        </div>
                                    @else
                                        <button
                                            wire:click="install('{{ $package->slug }}')"
                                            class="btn btn-primary btn-sm"
                                            wire:loading.attr="disabled"
                                            wire:target="install('{{ $package->slug }}')"
                                            @if($installRunning) disabled @endif
                                        >
                                            Installer
                                        </button>
                                    @endif
                                </div>
                            </article>
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-center text-muted py-4 mb-0">Aucune application dans le catalogue.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
