<div>
    @include('monitoring::partials.monitoring-nav')

    @php $s = $profile['server']; @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $s['name'] }}</h1>
            <p class="text-muted small mb-0">
                {{ $s['os_label'] ?: 'OS inconnu' }} · {{ $s['ip'] ?: 'IP —' }}
                @if($s['status'] === 'online')
                    <span class="badge text-bg-success ms-1">Online</span>
                @elseif($s['status'] === 'degraded')
                    <span class="badge text-bg-warning ms-1">Degraded</span>
                @else
                    <span class="badge text-bg-secondary ms-1">{{ $s['status'] }}</span>
                @endif
            </p>
            <p class="small text-muted mb-0">Dernière vue : {{ $s['last_seen'] ?: '—' }}</p>
        </div>
        <a href="{{ route('monitoring.servers') }}" class="btn btn-outline-secondary btn-sm">← Serveurs</a>
        <div class="d-flex flex-wrap gap-1">
            <a href="{{ route('monitoring.servers.metrics', $server) }}" class="btn btn-outline-primary btn-sm">Métriques</a>
            <a href="{{ route('monitoring.servers.sla-report', ['server' => $server, 'days' => 30]) }}" class="btn btn-outline-info btn-sm">Rapport SLA 30j</a>
            <a href="{{ route('monitoring.servers.sla-report', ['server' => $server, 'days' => 90]) }}" class="btn btn-outline-info btn-sm">SLA 90j</a>
        </div>
    </div>

    <ul class="nav nav-tabs obiora-nav-tabs mb-3">
        @foreach(['overview' => 'Vue d\'ensemble', 'actions' => 'Actions'] as $tab => $label)
            <li class="nav-item">
                <button type="button" @class(['nav-link', 'active' => $activeTab === $tab]) wire:click="setTab('{{ $tab }}')">{{ $label }}</button>
            </li>
        @endforeach
        @foreach($profile['links'] as $link)
            <li class="nav-item">
                <a href="{{ $link['route'] }}" class="nav-link">{{ $link['label'] }}</a>
            </li>
        @endforeach
    </ul>

    @if($activeTab === 'overview')
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4 col-xl">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Uptime SLA</div>
                    <div class="h4 mb-0">{{ number_format($sla['uptime']['30d'], 1) }}%</div>
                    <div class="small text-muted">30j · 60j {{ number_format($sla['uptime']['60d'], 1) }}% · 90j {{ number_format($sla['uptime']['90d'], 1) }}%</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Doctor</div>
                    @if($profile['doctor']['score'] !== null)
                        <div class="h4 mb-0">{{ $profile['doctor']['score'] }}%</div>
                        <div class="small">{{ $profile['doctor']['generated_at'] }}</div>
                    @else
                        <div class="text-muted">Aucun rapport</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Incidents monitoring</div>
                    <div class="h4 mb-0">{{ $profile['monitoring']['open_incidents'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">CrashHunter ouverts</div>
                    <div class="h4 mb-0">{{ $profile['crash_hunter']['open_incidents'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Dernier événement crash</div>
                    @if($profile['crash']['last_event'])
                        <div class="small fw-medium">{{ $profile['crash']['last_event']['message'] }}</div>
                        <div class="small text-muted">{{ $profile['crash']['last_event']['at'] }}</div>
                    @else
                        <div class="text-muted">—</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(count($profile['correlations']) > 0)
    <div class="card obiora-card mb-4 border-warning">
        <div class="card-header">Corrélations Monitor+</div>
        <div class="list-group list-group-flush">
            @foreach($profile['correlations'] as $hint)
            <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-medium">{{ $hint['label'] }}</span>
                    <span class="small text-muted d-block">{{ $hint['detail'] }}</span>
                </div>
                @if(!empty($hint['route']))
                    <a href="{{ $hint['route'] }}" class="btn btn-outline-warning btn-sm py-0">Voir</a>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(count($profile['monitoring']['open_incident_rows']) > 0)
    <div class="card obiora-card mb-4">
        <div class="card-header">Incidents ouverts</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head">
                    <tr><th>Déclencheur</th><th>Message</th><th>Depuis</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($profile['monitoring']['open_incident_rows'] as $incident)
                    <tr>
                        <td class="small text-warning">{{ $incident['trigger'] }}</td>
                        <td class="small">{{ $incident['message'] }}</td>
                        <td class="small text-nowrap">{{ $incident['went_down_at'] }}</td>
                        <td class="text-nowrap">
                            @foreach($incident['action_links'] as $link)
                                <a href="{{ $link['route'] }}" class="btn btn-outline-secondary btn-sm py-0 me-1">{{ $link['label'] }}</a>
                            @endforeach
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="card obiora-card">
        <div class="card-header">Monitor+ — accès rapide</div>
        <div class="card-body d-flex flex-wrap gap-2">
            @foreach($profile['links'] as $link)
                <a href="{{ $link['route'] }}" class="btn btn-outline-primary btn-sm">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>
    @endif

    @if($activeTab === 'actions')
    <div class="card obiora-card">
        <div class="card-body">
            <p class="small text-muted mb-3">Déploiement agents, diagnostics et outils forensics pour ce serveur.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('doctor.index', ['server' => $server->id]) }}" class="btn btn-primary btn-sm">Doctor & Suite</a>
                <a href="{{ route('crash-analyzer.index', ['server' => $server->id]) }}" class="btn btn-outline-warning btn-sm">Crash Analyzer</a>
                <a href="{{ route('monitoring.servers.metrics', $server) }}" class="btn btn-outline-info btn-sm">Métriques agent</a>
                <a href="{{ route('monitoring.fleet') }}" class="btn btn-outline-secondary btn-sm">Flotte avancée</a>
            </div>
        </div>
    </div>
    @endif

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }}</p>
</div>
