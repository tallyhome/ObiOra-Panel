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
    </div>

    <ul class="nav nav-tabs mb-3">
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
        <div class="col-md-3">
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
        <div class="col-md-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Incidents monitoring</div>
                    <div class="h4 mb-0">{{ $profile['monitoring']['open_incidents'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">CrashHunter ouverts</div>
                    <div class="h4 mb-0">{{ $profile['crash_hunter']['open_incidents'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
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
