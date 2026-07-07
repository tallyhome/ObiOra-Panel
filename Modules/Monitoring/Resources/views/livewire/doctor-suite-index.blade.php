<div>
    <div class="mb-4">
        <h1 class="h3 mb-1">ObiOra-Doctor & ObiOra-Suite</h1>
        <p class="text-muted mb-0">Diagnostic serveur et écosystème ObiOra depuis le panel seedbox</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h5">ObiOra-Doctor</h2>
                    <p class="text-muted small">Agent de diagnostic installé sur le serveur actif : <strong>{{ $server?->name ?? '—' }}</strong></p>

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
                        <div class="alert alert-warning py-2 small">Aucun rapport Doctor reçu pour ce serveur.</div>
                    @endif

                    <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary btn-sm">Ouvrir Monitoring</a>

                    <hr class="border-secondary opacity-25 my-3">
                    <p class="small text-muted mb-2">Installation agent (SSH root sur le VPS) :</p>
                    <pre class="small bg-dark p-2 rounded">{{ $installHint }}</pre>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h5">ObiOra-Suite</h2>
                    <p class="text-muted small">Site vitrine, démos et services complémentaires ObiOra.</p>
                    @if($suiteUrl !== '')
                        <a href="{{ $suiteUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Ouvrir ObiOra-Suite</a>
                    @else
                        <p class="small mb-0">Configurez <code>OBIORA_SUITE_URL</code> dans le <code>.env</code> pour lier la suite.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
