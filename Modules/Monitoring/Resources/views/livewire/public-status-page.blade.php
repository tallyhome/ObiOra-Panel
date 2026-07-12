<div class="container py-4" style="max-width: 960px;">
    @if($status['noindex'])
        <meta name="robots" content="noindex, nofollow">
    @endif

    <div class="text-center mb-4">
        <h1 class="h2 mb-2">{{ $status['title'] }}</h1>
        <p class="mb-1">
            @if($status['global_status'] === 'operational')
                <span class="badge text-bg-success fs-6">{{ $status['global_label'] }}</span>
            @elseif($status['global_status'] === 'partial_outage')
                <span class="badge text-bg-warning fs-6">{{ $status['global_label'] }}</span>
            @else
                <span class="badge text-bg-danger fs-6">{{ $status['global_label'] }}</span>
            @endif
        </p>
        <p class="small text-muted mb-0">Mis à jour : {{ $status['updated_at'] }}</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card obiora-card h-100 text-center">
                <div class="card-body">
                    <div class="small text-muted">Serveurs</div>
                    <div class="h4 mb-0">{{ $status['counts']['servers_online'] }}/{{ $status['counts']['servers_total'] }} online</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card obiora-card h-100 text-center">
                <div class="card-body">
                    <div class="small text-muted">Moniteurs</div>
                    <div class="h4 mb-0">{{ $status['counts']['monitors_up'] }}/{{ $status['counts']['monitors_total'] }} up</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card obiora-card h-100 text-center">
                <div class="card-body">
                    <div class="small text-muted">Incidents ouverts</div>
                    <div class="h4 mb-0">{{ $status['counts']['open_incidents'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card obiora-card mb-4">
        <div class="card-header">Serveurs</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head"><tr><th>Nom</th><th>Statut</th><th>Dernière vue</th></tr></thead>
                <tbody>
                    @forelse($status['servers'] as $server)
                    <tr>
                        <td>{{ $server['name'] }}</td>
                        <td>
                            @if($server['online'])
                                <span class="badge text-bg-success">Online</span>
                            @else
                                <span class="badge text-bg-secondary">Offline</span>
                            @endif
                        </td>
                        <td class="small">{{ $server['last_seen'] ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-muted small">Aucun serveur public.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card obiora-card mb-4">
        <div class="card-header">Moniteurs / sites</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head"><tr><th>Nom</th><th>Type</th><th>Statut</th><th>Uptime 30j</th><th>Réponse</th></tr></thead>
                <tbody>
                    @forelse($status['monitors'] as $monitor)
                    <tr>
                        <td>{{ $monitor['name'] }}</td>
                        <td class="small">{{ $monitor['type'] }}</td>
                        <td>
                            @if($monitor['status'] === 'up')
                                <span class="badge text-bg-success">Up</span>
                            @elseif($monitor['status'] === 'down')
                                <span class="badge text-bg-danger">Down</span>
                            @else
                                <span class="badge text-bg-secondary">Unknown</span>
                            @endif
                        </td>
                        <td>{{ number_format($monitor['uptime_30d'], 1) }}%</td>
                        <td>{{ $monitor['response_ms'] ? $monitor['response_ms'].' ms' : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted small">Aucun moniteur public.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card obiora-card">
        <div class="card-header">Incidents récents</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head"><tr><th>Ressource</th><th>Déclencheur</th><th>Statut</th><th>Début</th></tr></thead>
                <tbody>
                    @forelse($status['incidents'] as $incident)
                    <tr>
                        <td>{{ $incident['resource'] }}</td>
                        <td class="small">{{ $incident['trigger'] }}</td>
                        <td>
                            @if($incident['status'] === 'open')
                                <span class="badge text-bg-danger">Open</span>
                            @else
                                <span class="badge text-bg-success">Resolved</span>
                            @endif
                        </td>
                        <td class="small">{{ $incident['went_down_at'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted small">Aucun incident récent.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="small text-muted text-center mt-4 mb-0">Powered by ObiOra Panel</p>
</div>
