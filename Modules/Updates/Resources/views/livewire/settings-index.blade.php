<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Licence & Mises à jour</h1>
            <p class="text-muted mb-0">AdminLicence et mises à jour GitHub Releases</p>
        </div>
    </div>

    @if($licenseMessage)
        <div class="alert alert-{{ $licenseSuccess ? 'success' : 'danger' }}">{{ $licenseMessage }}</div>
    @endif

    @if($updateMessage)
        <div class="alert alert-{{ $updateSuccess ? 'success' : 'warning' }}">{{ $updateMessage }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Licence ObiOra</h2>

                    <dl class="row small mb-3">
                        <dt class="col-sm-4">Installation</dt>
                        <dd class="col-sm-8"><code class="small">{{ $installationUuid ?: 'N/A' }}</code></dd>
                        <dt class="col-sm-4">Plan</dt>
                        <dd class="col-sm-8"><span class="badge bg-primary">{{ $currentPlan }}</span></dd>
                        <dt class="col-sm-4">Statut</dt>
                        <dd class="col-sm-8">{{ $licenseStatus }}</dd>
                        <dt class="col-sm-4">AdminLicence</dt>
                        <dd class="col-sm-8">
                            @if($licenseEnabled && $adminLicenceUrl)
                                <span class="text-success">Activé</span>
                            @else
                                <span class="text-muted">Mode libre (validation locale)</span>
                            @endif
                        </dd>
                    </dl>

                    @can('license.manage')
                        <form wire:submit="activateLicense" class="mb-3">
                            <label class="form-label" for="licenseKey">Clé de licence</label>
                            <input wire:model="licenseKey" type="text" id="licenseKey" class="form-control mb-2" placeholder="OBIORA-XXXX-XXXX">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                    Activer
                                </button>
                                <button type="button" wire:click="refreshLicense" class="btn btn-outline-secondary btn-sm" wire:loading.attr="disabled">
                                    Synchroniser
                                </button>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Mises à jour du panel</h2>

                    <dl class="row small mb-3">
                        <dt class="col-sm-5">Version installée</dt>
                        <dd class="col-sm-7">v{{ $updateInfo['current'] ?? config('obiora.version') }}</dd>
                        <dt class="col-sm-5">Dernière release</dt>
                        <dd class="col-sm-7">
                            @if($updateInfo['latest'] ?? null)
                                v{{ $updateInfo['latest'] }}
                                @if($updateInfo['available'] ?? false)
                                    <span class="badge bg-warning text-dark ms-1">Disponible</span>
                                @else
                                    <span class="badge bg-success ms-1">À jour</span>
                                @endif
                            @else
                                <span class="text-muted">Indisponible</span>
                            @endif
                        </dd>
                    </dl>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button wire:click="checkUpdates" class="btn btn-outline-primary btn-sm" wire:loading.attr="disabled">
                            Vérifier
                        </button>
                        @can('updates.manage')
                            @if($updateInfo['available'] ?? false)
                                <button wire:click="applyUpdate" class="btn btn-primary btn-sm"
                                    wire:confirm="Appliquer la mise à jour maintenant ?"
                                    wire:loading.attr="disabled">
                                    Mettre à jour
                                </button>
                            @endif
                        @endcan
                        @if($updateInfo['changelog_url'] ?? null)
                            <a href="{{ $updateInfo['changelog_url'] }}" target="_blank" rel="noopener" class="btn btn-link btn-sm">
                                Changelog GitHub
                            </a>
                        @endif
                    </div>

                    <p class="text-muted small mb-0">
                        Les mises à jour sont téléchargées depuis GitHub Releases.
                        Avec AdminLicence activé, seules les installations licenciées pourront mettre à jour.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if($history->isNotEmpty())
        <div class="card obiora-card mt-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Historique des mises à jour</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>De</th>
                                <th>Vers</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $entry)
                                <tr>
                                    <td>v{{ $entry->from_version }}</td>
                                    <td>v{{ $entry->to_version }}</td>
                                    <td>
                                        <span class="badge bg-{{ $entry->status === 'completed' ? 'success' : ($entry->status === 'failed' ? 'danger' : 'secondary') }}">
                                            {{ $entry->status }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ $entry->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
