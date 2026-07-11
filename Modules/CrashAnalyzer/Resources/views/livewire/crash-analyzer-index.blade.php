<div>
    <div id="crash-analyzer-dashboard" class="d-none"
         data-dashboard='@json($dashboard)'
         data-poll-url="{{ $selectedServer ? route('crash-analyzer.api.dashboard', $selectedServer).'?minutes='.$historyMinutes : '' }}"
         data-history-minutes="{{ $historyMinutes }}"></div>

    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
        <div>
            <h1 class="h3 mb-1">Crash Analyzer</h1>
            <p class="text-muted small mb-0">Surveillance pré/post crash — métriques temps réel, événements critiques et rapports.</p>
        </div>
        @if($selectedServer)
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('crash-analyzer.export.json', $selectedServer) }}" class="btn btn-outline-secondary btn-sm">Export JSON</a>
            <a href="{{ route('crash-analyzer.export.csv', $selectedServer) }}" class="btn btn-outline-secondary btn-sm">Export CSV</a>
        </div>
        @endif
    </div>

    <div class="card obiora-card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label small mb-1">Serveur</label>
                    <select wire:model.live="server" class="form-select form-select-sm">
                        @foreach($servers as $srv)
                            <option value="{{ $srv->id }}">{{ $srv->name }} — {{ $srv->ip_address }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small mb-1">Historique (min)</label>
                    <select wire:model.live="historyMinutes" class="form-select form-select-sm">
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="60">60</option>
                        <option value="120">120</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-5">
                    <label class="form-label small mb-1">Filtrer type événement</label>
                    <input type="text" wire:model.live.debounce.300ms="eventTypeFilter" class="form-control form-control-sm" placeholder="oom_killer, rcu_stall…">
                </div>
                @if($dashboard)
                <div class="col-lg-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <div class="px-2 py-1 border rounded small"><span class="text-muted">Métriques</span> <strong>{{ $dashboard['summary']['metrics_count'] ?? 0 }}</strong></div>
                        <div class="px-2 py-1 border border-danger rounded small"><span class="text-muted">Critiques</span> <strong class="text-danger">{{ $dashboard['summary']['critical_events'] ?? 0 }}</strong></div>
                        <div class="px-2 py-1 border rounded small"><span class="text-muted">CPU max</span> <strong>{{ $dashboard['summary']['cpu_max'] ?? '—' }}%</strong></div>
                        <div class="px-2 py-1 border rounded small"><span class="text-muted">RAM max</span> <strong>{{ $dashboard['summary']['memory_max'] ?? '—' }}%</strong></div>
                    </div>
                </div>
                @endif
            </div>

            @if($selectedServer)
            <details class="mt-2">
                <summary class="small text-muted" style="cursor:pointer">Commande d'installation agent</summary>
                <code class="d-block small text-break p-2 bg-dark text-light rounded mt-1">
                    curl -fsSL {{ $panelUrl }}/install/crash-analyzer.sh | sudo OBIORA_PANEL_URL={{ $panelUrl }} OBIORA_SERVER_ID={{ $selectedServer->id }} OBIORA_AGENT_TOKEN=&lt;token&gt; bash
                </code>
            </details>
            @endif
        </div>
    </div>

    @if($dashboard)
    @if(!empty($dashboard['collectors']['active']))
    <div class="mb-3 d-flex flex-wrap gap-1 align-items-center">
        <span class="small text-muted me-1">Collecteurs actifs ({{ count($dashboard['collectors']['active']) }}) :</span>
        @foreach($dashboard['collectors']['active'] as $collector)
            <span class="badge text-bg-secondary">{{ $collector }} <span class="opacity-75">({{ $dashboard['collectors']['counts'][$collector] ?? 0 }})</span></span>
        @endforeach
    </div>
    @endif

    <div class="row g-3 mb-3" wire:ignore>
        <div class="col-xl-4 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header py-2 small">CPU % &amp; charge (load 1m)</div>
                <div class="card-body p-2" style="height:220px"><canvas id="chart-cpu"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header py-2 small">Mémoire RAM</div>
                <div class="card-body p-2" style="height:220px"><canvas id="chart-memory"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header py-2 small">Swap</div>
                <div class="card-body p-2" style="height:220px"><canvas id="chart-swap"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header py-2 small">PSI I/O (pression disque)</div>
                <div class="card-body p-2" style="height:220px"><canvas id="chart-psi-io"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header py-2 small">PSI mémoire</div>
                <div class="card-body p-2" style="height:220px"><canvas id="chart-psi-mem"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header py-2 small">Réseau &amp; température</div>
                <div class="row g-0 h-100">
                    <div class="col-6 p-2" style="height:220px"><canvas id="chart-network"></canvas></div>
                    <div class="col-6 p-2" style="height:220px"><canvas id="chart-thermal"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card obiora-card mb-3">
        <div class="card-header d-flex justify-content-between py-2">
            <span>Timeline des événements</span>
            <span class="badge bg-secondary">{{ count($dashboard['events'] ?? []) }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Date</th>
                        <th>Sévérité</th>
                        <th>Type</th>
                        <th>Titre</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dashboard['events'] ?? [] as $event)
                    <tr>
                        <td class="text-nowrap small">{{ $event['detected_at'] ?? '' }}</td>
                        <td>
                            <span class="badge {{ $event['severity'] === 'critical' ? 'text-bg-danger' : ($event['severity'] === 'warning' ? 'text-bg-warning' : 'text-bg-info') }}">
                                {{ $event['severity'] }}
                            </span>
                        </td>
                        <td><code class="small">{{ $event['event_type'] }}</code></td>
                        <td class="small">{{ $event['title'] }}</td>
                        <td class="small text-muted">{{ Str::limit($event['details'] ?? '', 120) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-4">Aucun événement sur la période</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card obiora-card">
        <div class="card-header py-2">Rapports post-crash</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head">
                    <tr><th>ID</th><th>Déclencheur</th><th>Généré</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($dashboard['reports'] ?? [] as $report)
                    <tr>
                        <td><code class="small">{{ $report['external_id'] ?? $report['id'] }}</code></td>
                        <td>{{ $report['trigger_type'] ?? '—' }}</td>
                        <td class="small text-nowrap">{{ $report['generated_at'] ?? '' }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('crash-analyzer.report.view', [$selectedServer, $report['id']]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">Voir</a>
                            <a href="{{ route('crash-analyzer.export.pdf', [$selectedServer, $report['id']]) }}" class="btn btn-sm btn-outline-secondary">PDF/HTML</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted text-center py-3">Aucun rapport</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="alert alert-info mb-0">Sélectionnez un serveur pour afficher les métriques Crash Analyzer.</div>
    @endif
</div>

@assets
@vite(['resources/js/crash-analyzer/main.js'])
@endassets
