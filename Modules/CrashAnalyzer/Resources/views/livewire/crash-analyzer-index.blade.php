<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Crash Analyzer</h1>
            <p class="text-muted mb-0">Surveillance pré/post crash — métriques, événements critiques et rapports automatiques.</p>
        </div>
        @if($selectedServer)
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('crash-analyzer.export.json', $selectedServer) }}" class="btn btn-outline-secondary btn-sm">Export JSON</a>
            <a href="{{ route('crash-analyzer.export.csv', $selectedServer) }}" class="btn btn-outline-secondary btn-sm">Export CSV</a>
        </div>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Serveur</label>
                    <select wire:model.live="server" class="form-select">
                        @foreach($servers as $srv)
                            <option value="{{ $srv->id }}">{{ $srv->name }} ({{ $srv->hostname }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Historique (min)</label>
                    <select wire:model.live="historyMinutes" class="form-select">
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="60">60</option>
                        <option value="120">120</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Filtrer type événement</label>
                    <input type="text" wire:model.live.debounce.300ms="eventTypeFilter" class="form-control" placeholder="oom_killer, reboot…">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Installation agent</label>
                    @if($selectedServer)
                    <code class="d-block small text-break p-2 bg-dark text-light rounded">
                        curl -fsSL {{ $panelUrl }}/install/crash-analyzer.sh | sudo OBIORA_PANEL_URL={{ $panelUrl }} OBIORA_SERVER_ID={{ $selectedServer->id }} OBIORA_AGENT_TOKEN=&lt;token&gt; bash
                    </code>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($dashboard)
    <div id="crash-analyzer-dashboard"
         data-dashboard='@json($dashboard)'
         data-poll-url="{{ route('crash-analyzer.api.dashboard', $selectedServer) }}?minutes={{ $historyMinutes }}"
         data-history-minutes="{{ $historyMinutes }}">
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Métriques</div>
                    <div class="h4 mb-0">{{ $dashboard['summary']['metrics_count'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Événements critiques</div>
                    <div class="h4 mb-0 text-danger">{{ $dashboard['summary']['critical_events'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">CPU max</div>
                    <div class="h4 mb-0">{{ $dashboard['summary']['cpu_max'] ?? '—' }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">RAM max</div>
                    <div class="h4 mb-0">{{ $dashboard['summary']['memory_max'] ?? '—' }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">CPU & charge</div>
                <div class="card-body"><canvas id="chart-cpu" height="200"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Mémoire</div>
                <div class="card-body"><canvas id="chart-memory" height="200"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">IO Wait disque</div>
                <div class="card-body"><canvas id="chart-disk" height="200"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Connexions TCP</div>
                <div class="card-body"><canvas id="chart-network" height="200"></canvas></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between">
            <span>Timeline des événements</span>
            <span class="badge bg-secondary">{{ count($dashboard['events'] ?? []) }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
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
                            <span class="badge bg-{{ $event['severity'] === 'critical' ? 'danger' : ($event['severity'] === 'warning' ? 'warning text-dark' : 'info') }}">
                                {{ $event['severity'] }}
                            </span>
                        </td>
                        <td><code>{{ $event['event_type'] }}</code></td>
                        <td>{{ $event['title'] }}</td>
                        <td class="small text-muted">{{ Str::limit($event['details'] ?? '', 120) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-4">Aucun événement sur la période</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Rapports post-crash</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr><th>ID</th><th>Déclencheur</th><th>Généré</th><th>Export</th></tr>
                </thead>
                <tbody>
                    @forelse($dashboard['reports'] ?? [] as $report)
                    <tr>
                        <td><code>{{ $report['external_id'] ?? $report['id'] }}</code></td>
                        <td>{{ $report['trigger_type'] ?? '—' }}</td>
                        <td>{{ $report['generated_at'] ?? '' }}</td>
                        <td>
                            <a href="{{ route('crash-analyzer.export.pdf', [$selectedServer, $report['id']]) }}" class="btn btn-sm btn-outline-primary">PDF/HTML</a>
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
    <div class="alert alert-info">Sélectionnez un serveur pour afficher les métriques Crash Analyzer.</div>
    @endif
</div>

@assets
@vite(['resources/js/crash-analyzer/main.js'])
@endassets
