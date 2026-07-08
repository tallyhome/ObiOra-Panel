<div>
    <div class="mb-4">
        <h1 class="h3 mb-1">ObiOra-Doctor & ObiOra-Suite</h1>
        <p class="text-muted mb-0">Agent de diagnostic léger — fonctionne sans dépôt ObiOra-Doctor sur le serveur cible.</p>
    </div>

    <div class="alert alert-secondary py-2 small mb-4">
        <strong>Variables d'installation</strong> —
        <code>OBIORA_PANEL_URL</code> : adresse du panel (où envoyer les rapports, ex. <code>http://54.37.103.239</code>) ·
        <code>OBIORA_SERVER_ID</code> : numéro du serveur dans le panel ·
        <code>OBIORA_AGENT_TOKEN</code> : jeton secret lié à ce serveur (généré automatiquement en BDD).
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h5">ObiOra-Doctor</h2>
                    <p class="text-muted small mb-3">
                        Serveur actif : <strong>{{ $server?->name ?? '—' }}</strong>
                        (ID {{ $server?->id ?? '—' }})
                    </p>

                    @if($report)
                        <dl class="row small mb-3">
                            <dt class="col-sm-4">Score</dt>
                            <dd class="col-sm-8">{{ $report->score }}%</dd>
                            <dt class="col-sm-4">Statut</dt>
                            <dd class="col-sm-8">{{ $report->status }}</dd>
                            <dt class="col-sm-4">Généré</dt>
                            <dd class="col-sm-8">{{ $report->generated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </dl>
                    @else
                        <div class="alert alert-warning py-2 small">Aucun rapport Doctor reçu — installez l'agent ci-dessous.</div>
                    @endif

                    <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary btn-sm mb-4">Ouvrir Monitoring</a>

                    <h3 class="h6">1. Sur ce serveur (panel local)</h3>
                    <p class="small text-muted">En SSH root sur la machine où tourne le panel :</p>
                    <div class="obiora-copy-block mb-3">
                        <pre class="small mb-0 obiora-copy-text">{{ $localInstall }}</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="obioraCopyFromButton(this)">Copier</button>
                    </div>

                    <h3 class="h6">2. Sur un autre VPS (serveur distant)</h3>
                    <p class="small text-muted">En root sur le serveur à monitorer (token lié au serveur #{{ $server?->id }}) :</p>
                    <div class="obiora-copy-block">
                        <pre class="small mb-0 obiora-copy-text">{{ $remoteInstall }}</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="obioraCopyFromButton(this)">Copier</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h5">ObiOra-Suite</h2>
                    <p class="text-muted small">Site vitrine et services complémentaires ObiOra.</p>
                    @if($suiteUrl !== '')
                        <a href="{{ $suiteUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Ouvrir ObiOra-Suite</a>
                    @else
                        <p class="small mb-0">Ajoutez <code>OBIORA_SUITE_URL</code> dans le <code>.env</code>.</p>
                    @endif
                    <hr class="border-secondary opacity-25 my-3">
                    <p class="small text-muted mb-0">
                        L'agent installe un timer systemd <code>obiora-doctor-agent</code> (scan toutes les 5 min)
                        et envoie les rapports au panel via l'API <code>/api/v1/servers/{id}/diagnostics/reports</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
