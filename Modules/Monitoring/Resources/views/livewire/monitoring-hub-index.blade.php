<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-muted small mb-0">Vue d'ensemble serveurs, moniteurs et incidents.</p>
        </div>
        @if($canManageServers)
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                + Ajouter
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('monitoring.servers', ['add' => 1]) }}">Serveur</a></li>
                <li><a class="dropdown-item" href="{{ route('monitoring.monitors', ['add' => 1]) }}">Moniteur</a></li>
            </ul>
        </div>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Serveurs</div>
                    <div class="display-6 fw-semibold">{{ $summary['servers_total'] }}</div>
                    <div class="small mt-2">
                        <span class="text-success">● {{ $summary['servers_online'] }} en ligne</span>
                        <span class="text-danger ms-2">● {{ $summary['servers_offline'] }} hors ligne</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Moniteurs</div>
                    <div class="display-6 fw-semibold">{{ $summary['monitors_total'] }}</div>
                    <div class="small mt-2">
                        <span class="text-success">● {{ $summary['monitors_up'] }} up</span>
                        <span class="text-danger ms-2">● {{ $summary['monitors_down'] }} down</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card obiora-card h-100 border-warning border-opacity-25">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Incidents ouverts</div>
                    <div class="display-6 fw-semibold text-warning">{{ $summary['open_incidents'] }}</div>
                    <div class="small mt-2">
                        @if($summary['open_incidents'] > 0)
                            <a href="{{ route('monitoring.incidents') }}">Voir les incidents →</a>
                        @else
                            <span class="text-muted">Aucun incident actif</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Plateforme</div>
                    <div class="fw-semibold">{{ $summary['plan_label'] }}</div>
                    <div class="small mt-2 text-muted">Self-hosted ObiOra Panel</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Serveurs</span>
                    <a href="{{ route('monitoring.servers') }}" class="small">Tout voir</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm obiora-table mb-0">
                        <thead class="obiora-table-head">
                            <tr><th>Nom</th><th>Statut</th><th>Dernière vue</th></tr>
                        </thead>
                        <tbody>
                            @forelse($recentServers as $row)
                            <tr>
                                <td class="fw-medium">{{ $row['name'] }}</td>
                                <td>
                                    @if($row['online'] || ($row['status'] ?? '') === 'online')
                                        <span class="badge text-bg-success">Online</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ $row['status'] ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $row['last_seen'] ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted small">Aucun serveur.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Moniteurs</span>
                    <a href="{{ route('monitoring.monitors') }}" class="small">Tout voir</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm obiora-table mb-0">
                        <thead class="obiora-table-head">
                            <tr><th>Nom</th><th>Type</th><th>Statut</th><th>Réponse</th></tr>
                        </thead>
                        <tbody>
                            @forelse($recentMonitors as $row)
                            <tr>
                                <td class="fw-medium">
                                    <a href="{{ route('monitoring.monitors.show', $row['id']) }}">{{ $row['name'] }}</a>
                                </td>
                                <td><span class="badge text-bg-secondary">{{ $row['type_label'] }}</span></td>
                                <td>
                                    @if(($row['status'] ?? '') === 'up')
                                        <span class="badge text-bg-success">Up</span>
                                    @elseif(($row['status'] ?? '') === 'down')
                                        <span class="badge text-bg-danger">Down</span>
                                    @else
                                        <span class="badge text-bg-secondary">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $row['response_ms'] ? $row['response_ms'].' ms' : '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-muted small">Aucun moniteur.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if(count($witnessSummary) > 0)
    <div class="card obiora-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>CrashHunter Witness (flotte)</span>
            @if($witnessAnomalies > 0)
                <span class="badge text-bg-warning">{{ $witnessAnomalies }} anomalie(s) — ping OK mais agent witness mort</span>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head">
                    <tr><th>Serveur</th><th>Ping</th><th>Witness</th><th>Vu</th></tr>
                </thead>
                <tbody>
                    @foreach($witnessSummary as $row)
                    <tr @class(['table-warning' => $row['anomaly']])>
                        <td><a href="{{ route('monitoring.servers.show', $row['server_id']) }}">{{ $row['server_name'] }}</a></td>
                        <td>{{ $row['ping_ok'] ? 'OK' : 'KO' }}</td>
                        <td>
                            @if($row['witness_status'] === 'not_installed')
                                <span class="text-muted">non installé</span>
                            @else
                                {{ $row['witness_status'] }}
                            @endif
                        </td>
                        <td class="small">{{ $row['witness_last_at'] ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="card obiora-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Incidents ouverts</span>
            <a href="{{ route('monitoring.incidents') }}" class="small">Tout voir</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Ressource</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Début</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($openIncidents as $incident)
                    <tr>
                        <td>{{ $incident['resource'] }}</td>
                        <td><span class="text-warning">{{ $incident['trigger'] }}</span></td>
                        <td class="small">{{ Str::limit($incident['message'], 60) }}</td>
                        <td class="small text-nowrap">{{ $incident['went_down_at'] }}</td>
                        <td><span class="badge text-bg-danger">Open</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted small">Aucun incident ouvert.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="small text-muted mb-0">
        Toutes les heures sont en {{ $timezoneFooter }}. Maintenant : {{ $nowLabel }}.
    </p>
</div>
