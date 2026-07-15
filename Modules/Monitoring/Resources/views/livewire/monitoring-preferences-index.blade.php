<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="mb-4">
        <h1 class="h3 mb-1">Préférences</h1>
        <p class="text-muted small mb-0">Fuseau horaire, rétention des données et export Prometheus.</p>
    </div>

    <ul class="nav nav-tabs obiora-nav-tabs mb-3">
        <li class="nav-item">
            <a href="{{ route('monitoring.preferences') }}" @class(['nav-link', 'active' => $activeTab === 'timezone'])>Fuseau horaire</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('monitoring.settings.retention') }}" @class(['nav-link', 'active' => $activeTab === 'retention'])>Rétention & Prometheus</a>
        </li>
    </ul>

    @if($activeTab === 'timezone')
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card obiora-card">
                <div class="card-header">Fuseau horaire</div>
                <div class="card-body">
                    <label class="form-label small fw-medium" for="userTimezone">Timezone</label>
                    <select id="userTimezone" wire:model.live="timezone" class="form-select mb-2">
                        @foreach($timezoneChoices as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="small text-muted mb-3">
                        Toutes les dates du monitoring s'affichent dans ce fuseau.
                        <br>Aperçu : <strong>{{ $previewTime }}</strong>
                    </p>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save" wire:loading.attr="disabled">
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card obiora-card">
                <div class="card-header">Profil</div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nom</dt>
                        <dd class="col-sm-8">{{ auth()->user()?->name }}</dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8">{{ auth()->user()?->email }}</dd>
                    </dl>
                    <p class="text-muted mt-3 mb-0">Licence et mises à jour : <a href="{{ route('settings.index') }}">Paramètres panel</a>.</p>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card obiora-card">
                <div class="card-header">Rétention des données (panel)</div>
                <div class="card-body small">
                    <p class="text-muted">Durées configurées via <code>.env</code> — purge nocturne automatique.</p>
                    <dl class="row mb-0">
                        <dt class="col-sm-6">Échantillons ping flotte</dt>
                        <dd class="col-sm-6"><strong>{{ $retention['ping_days'] }} jours</strong> — <code>OBIORA_MONITOR_RETENTION_DAYS</code></dd>
                        <dt class="col-sm-6">Métriques serveur (CPU/RAM…)</dt>
                        <dd class="col-sm-6"><strong>{{ $retention['sample_days'] }} jours</strong> — <code>OBIORA_MONITOR_SAMPLE_RETENTION_DAYS</code></dd>
                        <dt class="col-sm-6">Checks moniteurs web</dt>
                        <dd class="col-sm-6"><strong>{{ $retention['check_days'] }} jours</strong> — <code>OBIORA_MONITOR_CHECK_RETENTION_DAYS</code></dd>
                    </dl>
                    <p class="mt-3 mb-0">
                        Purge manuelle : <code>php artisan obiora:prune --dry-run</code> puis <code>php artisan obiora:prune</code>.
                        <br>Documentation : <code>docs/monitoring/RETENTION-ET-PURGE.md</code>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card obiora-card">
                <div class="card-header">Export Prometheus / Grafana</div>
                <div class="card-body small">
                    @if($retention['prometheus_enabled'])
                        <p class="mb-2"><span class="badge text-bg-success">Activé</span> endpoint <code>/metrics</code></p>
                        <p class="text-muted mb-0">Configurez Grafana ou Prometheus pour scraper le panel avec le token Bearer. Voir <code>docs/monitoring/GRAFANA-PONT.md</code>.</p>
                    @else
                        <p class="mb-2"><span class="badge text-bg-secondary">Désactivé</span></p>
                        <pre class="small bg-body-secondary p-2 rounded mb-0">OBIORA_PROMETHEUS_ENABLED=true
OBIORA_PROMETHEUS_TOKEN=votre-token-secret</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>
</div>
