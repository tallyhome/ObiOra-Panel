<div @if($updateRunning) wire:poll.3s="pollUpdateStatus" @endif>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Licence & Mises à jour</h1>
            <p class="text-muted mb-0">AdminLicence et mises à jour GitHub Releases</p>
        </div>
    </div>

    @if($licenseMessage)
        <div class="alert alert-{{ $licenseSuccess ? 'success' : 'danger' }}">{{ $licenseMessage }}</div>
    @endif

    @if($updateRunning)
        <div class="card obiora-card mb-4 border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small text-uppercase">Mise à jour en cours</strong>
                    <span class="badge bg-info">{{ $updateProgress }}%</span>
                </div>
                <div class="obiora-progress info mb-2" style="height: 12px;">
                    <div class="bar" style="width: {{ max(2, $updateProgress) }}%"></div>
                </div>
                <p class="mb-0 small text-muted d-flex align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    {{ $updateProgressMessage ?: ($updateMessage ?? 'Mise à jour en cours…') }}
                </p>
            </div>
        </div>
    @elseif($updateMessage)
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
                        <dt class="col-sm-5">Canal</dt>
                        <dd class="col-sm-7"><span class="badge bg-secondary">stable</span></dd>
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
                        @if(($updateInfo['commits_behind'] ?? 0) > 0)
                            <dt class="col-sm-5">Git main</dt>
                            <dd class="col-sm-7"><span class="badge bg-warning text-dark">{{ $updateInfo['commits_behind'] }} commit(s) en retard</span></dd>
                        @endif
                    </dl>

                    @if($updateInfo['available'] ?? false)
                        <div class="alert alert-warning py-2 small mb-3">
                            Une mise à jour est disponible. Cliquez sur « Mettre à jour » pour appliquer.
                        </div>
                    @elseif(!empty($updateInfo['error']))
                        <div class="alert alert-danger py-2 small mb-3">
                            {{ $updateInfo['error'] }}
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                        <button wire:click="checkUpdates" class="btn btn-outline-primary btn-sm" wire:loading.attr="disabled" @if($updateRunning) disabled @endif>
                            <span wire:loading.remove wire:target="checkUpdates">Vérifier</span>
                            <span wire:loading wire:target="checkUpdates">↻ Vérification…</span>
                        </button>
                        @can('updates.manage')
                            @if($updateRunning)
                                <button type="button" class="btn btn-primary btn-sm" disabled>
                                    <span class="spinner-border spinner-border-sm me-1"></span> Mise à jour en cours…
                                </button>
                            @elseif($updateInfo['available'] ?? false)
                                <button type="button" wire:loading.attr="disabled"
                                    onclick="obioraConfirmWire(this, 'applyUpdate', 'Mettre à jour', 'Appliquer la mise à jour maintenant ? Le panel sera indisponible quelques instants.')"
                                    class="btn btn-primary btn-sm">
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

                    @if($lastCheckedAt)
                        <p class="text-muted small mb-3">Dernière vérification : {{ $lastCheckedAt }}</p>
                    @endif

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
                <p class="text-muted small mb-3">
                    Les entrées <span class="badge bg-danger">failed</span> correspondent à d'anciennes tentatives (ex. avant v1.9.6).
                    Elles restent en historique à titre informatif et n'empêchent pas les mises à jour suivantes.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>De</th>
                                <th>Vers</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th class="text-end">Log</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $entry)
                                <tr>
                                    <td>v{{ $entry->from_version }}</td>
                                    <td>v{{ $entry->to_version }}</td>
                                    <td>
                                        @php
                                            $badgeColor = match($entry->status) {
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                'running' => 'info',
                                                'queued' => 'warning',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $badgeColor }}{{ $badgeColor === 'warning' ? ' text-dark' : '' }}">
                                            {{ $entry->status }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ $entry->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="text-end">
                                        @if (! empty($entry->output))
                                            <button type="button" wire:click="showHistoryOutput({{ $entry->id }})" class="btn btn-outline-secondary btn-sm py-0">Voir le log</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($viewingOutputId)
                    <div class="card obiora-card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <span class="small fw-medium">Log complet — MAJ #{{ $viewingOutputId }}</span>
                            <button type="button" class="btn-close btn-close-white btn-sm" wire:click="closeHistoryOutput"></button>
                        </div>
                        <div class="card-body p-0">
                            <pre class="small mb-0 p-3 obiora-log-pre">{{ $viewingOutput }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
