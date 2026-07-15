<div @if($updateRunning || $systemUpdateRunning) wire:poll.3s="pollUpdateStatus" @endif>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Licence & Mises à jour</h1>
            <p class="text-muted mb-0">Licence et mises à jour GitHub Releases</p>
        </div>
    </div>

    @if($licenseMessage)
        <div class="alert alert-{{ $licenseSuccess ? 'success' : 'danger' }}">{{ $licenseMessage }}</div>
    @endif

    @if(!$updateSuccess && $updateMessage && $updateRunning)
        <div class="alert alert-danger mb-4 obiora-update-error-banner" role="alert">
            <strong>Problème détecté</strong> — {{ $updateMessage }}
            @can('updates.manage')
                <button type="button" class="btn btn-outline-danger btn-sm ms-2"
                    onclick="obioraConfirmWire(this, 'cancelBlockedUpdate', 'Débloquer la MAJ', 'Marquer la mise à jour comme échouée et purger les caches ?')">
                    Débloquer
                </button>
            @endcan
        </div>
    @endif

    @if($updateRunning)
        <div class="obiora-update-overlay" role="dialog" aria-modal="true" aria-labelledby="update-overlay-title">
            <div class="obiora-update-overlay-panel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong id="update-overlay-title" class="small text-uppercase text-info">Mise à jour en cours</strong>
                    <span class="badge bg-info">{{ $updateProgress }}%</span>
                </div>
                <div class="obiora-progress info mb-3" style="height: 12px;">
                    <div class="bar" style="width: {{ max(2, $updateProgress) }}%"></div>
                </div>
                <p class="mb-2 small text-muted d-flex align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    {{ $updateProgressMessage ?: ($updateMessage ?? 'Mise à jour en cours…') }}
                </p>
                <p class="mb-0 small text-muted">
                    Ne fermez pas cette page — elle se rafraîchira automatiquement. Le panel peut afficher brièvement une page plein écran pendant l'installation.
                </p>
            </div>
        </div>
    @elseif($updateMessage)
        <div class="alert alert-{{ $updateSuccess ? 'success' : 'danger' }} mb-4">{{ $updateMessage }}</div>
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
                        <dd class="col-sm-7">
                            <span class="badge bg-secondary">stable</span>
                            <span class="text-muted small ms-1">— pipeline MAJ validé</span>
                        </dd>
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

                    @if(($updateInfo['available'] ?? false) && !empty($availableReleaseNotes))
                        <div class="border rounded p-3 mb-3 bg-dark bg-opacity-25">
                            <h3 class="h6 mb-2">Notes v{{ $updateInfo['latest'] ?? '?' }}</h3>
                            <pre class="small text-muted mb-0" style="white-space: pre-wrap; font-family: inherit;">{{ $availableReleaseNotes }}</pre>
                        </div>
                    @endif

                    <p class="text-muted small mb-0">
                        Les mises à jour sont téléchargées depuis GitHub Releases.
                        Avec AdminLicence activé, seules les installations licenciées pourront mettre à jour.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @can('updates.manage')
        @if(($systemInfo['can_update'] ?? false) || ($systemInfo['can_reboot'] ?? false))
            <div class="card obiora-card mt-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Maintenance système</h2>
                    <p class="text-muted small mb-3">
                        Met à jour les paquets du système d'exploitation ({{ $systemInfo['manager'] ?? 'inconnu' }}) et permet de planifier un redémarrage du serveur.
                    </p>

                    @if($systemMessage)
                        <div class="alert alert-{{ $systemSuccess ? 'success' : 'danger' }} py-2 small">{{ $systemMessage }}</div>
                    @endif

                    @if($systemUpdateRunning)
                        <div class="d-flex align-items-center gap-2 small text-muted mb-3">
                            <span class="spinner-border spinner-border-sm"></span>
                            Mise à jour système en cours…
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2">
                        @if($systemInfo['can_update'] ?? false)
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="queueSystemUpdate" wire:loading.attr="disabled" @if($systemUpdateRunning) disabled @endif>
                                Mettre à jour le système
                            </button>
                        @endif
                        @if($systemInfo['can_reboot'] ?? false)
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                onclick="obioraConfirmWire(this, 'scheduleSystemReboot', 'Redémarrer le serveur', 'Planifier un redémarrage dans environ 1 minute ? Le panel sera indisponible.')">
                                Redémarrer
                            </button>
                        @endif
                    </div>

                    @if($systemOutput)
                        <pre class="small mt-3 mb-0 p-3 rounded obiora-log-pre">{{ $systemOutput }}</pre>
                    @endif
                </div>
            </div>
        @endif
    @elseif($isDemoAccount)
        <div class="card obiora-card mt-4 obiora-card-disabled">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <h2 class="h5 mb-0 text-muted">Maintenance système</h2>
                    <span class="badge bg-secondary">Compte démo</span>
                </div>
                <p class="text-muted small mb-3">
                    La maintenance du système d'exploitation (mises à jour paquets, redémarrage serveur) n'est pas disponible sur un compte démo.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Mettre à jour le système</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Redémarrer</button>
                </div>
            </div>
        </div>
    @endcan

    @if($history->isNotEmpty() || !empty($changelogSections))
        <div class="row g-4 mt-4">
            @if($history->isNotEmpty())
                <div class="col-lg-6">
                    <div class="card obiora-card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h2 class="h6 mb-1">Historique des mises à jour</h2>
                                    <p class="text-muted small mb-0">
                                        Les entrées <span class="badge bg-danger">failed</span> restent visibles à titre informatif.
                                    </p>
                                </div>
                                @if($updateRunning)
                                    <button type="button" class="btn btn-outline-warning btn-sm"
                                        onclick="obioraConfirmWire(this, 'cancelBlockedUpdate', 'Réinitialiser la MAJ', 'Marquer la mise à jour bloquée comme échouée et purger les caches ?')">
                                        Débloquer
                                    </button>
                                @endif
                            </div>
                            <div class="table-responsive flex-grow-1">
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
                                                        <button type="button" wire:click="showHistoryOutput({{ $entry->id }})" class="btn btn-outline-secondary btn-sm py-0">Log</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(!empty($changelogSections))
                <div class="col-lg-6">
                    <div class="card obiora-card h-100">
                        <div class="card-body">
                            <h2 class="h6 mb-1">Journal des versions</h2>
                            <p class="text-muted small mb-3">Changelog intégré depuis <code>CHANGELOG.md</code>.</p>
                            <div class="accordion accordion-flush obiora-changelog-accordion" id="changelogAccordion">
                                @foreach($changelogSections as $index => $section)
                                    <div class="accordion-item bg-transparent border-secondary">
                                        <h3 class="accordion-header">
                                            <button class="accordion-button collapsed bg-transparent text-light shadow-none py-2"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#changelog-{{ $index }}">
                                                v{{ $section['version'] }}
                                                @if($section['date'])
                                                    <span class="text-muted small ms-2">{{ $section['date'] }}</span>
                                                @endif
                                            </button>
                                        </h3>
                                        <div id="changelog-{{ $index }}" class="accordion-collapse collapse" data-bs-parent="#changelogAccordion">
                                            <div class="accordion-body pt-0">
                                                @if(!empty($section['items']))
                                                    <ul class="small mb-0">
                                                        @foreach($section['items'] as $item)
                                                            <li>{{ $item }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <pre class="small text-muted mb-0" style="white-space: pre-wrap; font-family: inherit;">{{ $section['body'] }}</pre>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($viewingOutputId)
        <div class="obiora-log-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="log-modal-title">
            <div class="obiora-log-modal" wire:click.stop>
                <div class="obiora-log-modal-header">
                    <span id="log-modal-title" class="small fw-medium">Log complet — MAJ #{{ $viewingOutputId }}</span>
                    <button type="button" class="btn-close btn-close-white btn-sm" wire:click="closeHistoryOutput" aria-label="Fermer"></button>
                </div>
                <div class="obiora-log-modal-body">
                    <pre class="small mb-0 obiora-log-pre">{{ $viewingOutput }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
