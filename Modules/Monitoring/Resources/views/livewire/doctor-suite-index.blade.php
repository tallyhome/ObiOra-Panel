<div>
    <div class="mb-4">
        <h1 class="h3 mb-1">ObiOra-Doctor & ObiOra-Suite</h1>
        <p class="text-muted mb-0">Agent de diagnostic léger — fonctionne sans dépôt ObiOra-Doctor sur le serveur cible.</p>
    </div>

    <div class="alert alert-secondary py-2 small mb-4">
        <strong>Variables d'installation</strong> —
        <code>OBIORA_PANEL_URL</code> : adresse du panel ·
        <code>OBIORA_SERVER_ID</code> : ID serveur dans le panel ·
        <code>OBIORA_AGENT_TOKEN</code> : jeton secret du serveur.
        @if($reportCount > 0)
            <span class="ms-2 text-success">· {{ $reportCount }} rapport(s) en base@if($lastReportAt) — dernier {{ \Illuminate\Support\Carbon::parse($lastReportAt)->format('d/m/Y H:i') }}@endif</span>
        @endif
    </div>

    @if($doctorFleet->isNotEmpty())
        <div class="card obiora-card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">État Doctor — tous les serveurs</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Serveur</th>
                                <th>ID</th>
                                <th>Score</th>
                                <th>Statut</th>
                                <th>Dernier rapport</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($doctorFleet as $fleetServer)
                                @php($fleetReport = $fleetServer->latestDiagnosticReport)
                                <tr @class(['table-active' => $fleetServer->id === $server?->id])>
                                    <td>{{ $fleetServer->name }}</td>
                                    <td><code>{{ $fleetServer->id }}</code></td>
                                    <td>
                                        @if($fleetReport)
                                            <span class="badge text-bg-success">{{ $fleetReport->score }}%</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $fleetReport?->status ?? '—' }}</td>
                                    <td class="small text-muted">{{ $fleetReport?->generated_at?->format('d/m/Y H:i') ?? 'Aucun' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h5">ObiOra-Doctor</h2>
                    <p class="text-muted small mb-3">
                        Serveur sélectionné : <strong>{{ $server?->name ?? '—' }}</strong>
                        (ID {{ $server?->id ?? '—' }})
                    </p>

                    @if($report)
                        <dl class="row small mb-3">
                            <dt class="col-sm-4">Score</dt>
                            <dd class="col-sm-8"><span class="badge text-bg-success">{{ $report->score }}%</span></dd>
                            <dt class="col-sm-4">Statut</dt>
                            <dd class="col-sm-8">{{ $report->status }}</dd>
                            <dt class="col-sm-4">Version agent</dt>
                            <dd class="col-sm-8">{{ $report->doctor_version ?: '—' }}</dd>
                            <dt class="col-sm-4">Généré</dt>
                            <dd class="col-sm-8">{{ $report->generated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                            <dt class="col-sm-4">Reçu</dt>
                            <dd class="col-sm-8">{{ $report->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </dl>
                    @else
                        <div class="alert alert-warning py-2 small">
                            Aucun rapport pour le serveur #{{ $server?->id ?? '?' }}.
                            @if($reportCount > 0)
                                Des rapports existent pour d'autres serveurs (voir tableau ci-dessus) — vérifiez que <code>OBIORA_SERVER_ID</code> correspond au bon ID.
                            @else
                                Installez l'agent ci-dessous.
                            @endif
                        </div>
                    @endif

                    <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary btn-sm mb-4">Ouvrir Monitoring</a>

                    <h3 class="h6">1. Sur ce serveur (panel local)</h3>
                    <div class="obiora-copy-block mb-3">
                        <pre class="small mb-0 obiora-copy-text">{{ $localInstall }}</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="obioraCopyFromButton(this)">Copier</button>
                    </div>

                    <h3 class="h6">2. Sur un autre VPS (serveur distant)</h3>
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
                        Timer systemd <code>obiora-doctor-agent</code> (scan / 5 min) → API
                        <code>/api/v1/servers/{id}/diagnostics/reports</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
