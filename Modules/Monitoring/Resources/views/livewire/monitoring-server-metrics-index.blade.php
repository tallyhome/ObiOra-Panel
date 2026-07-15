<div>
    @include('monitoring::partials.monitoring-nav')

    @php $header = $dashboard['server']; @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $header['name'] }}</h1>
            <p class="text-muted small mb-0">
                {{ $header['os_label'] ?: 'OS inconnu' }}
                @if($header['kernel']) — {{ $header['kernel'] }} @endif
                · Agent {{ $header['agent_version'] }}
                @if($header['status'] === 'online')
                    <span class="badge text-bg-success ms-1">Online</span>
                @elseif($header['status'] === 'degraded')
                    <span class="badge text-bg-warning ms-1">Degraded</span>
                @else
                    <span class="badge text-bg-secondary ms-1">{{ $header['status'] }}</span>
                @endif
            </p>
            <p class="small text-muted mb-0">Dernière vue : {{ $header['last_seen'] ?: '—' }} · {{ $header['ip_address'] }}</p>
        </div>
        <a href="{{ route('monitoring.servers') }}" class="btn btn-outline-secondary btn-sm">← Serveurs</a>
    </div>

    <div class="d-flex flex-wrap gap-1 mb-3" wire:loading.class="opacity-50" wire:target="setPreset">
        @foreach($presets as $preset)
            <button type="button" wire:click="setPreset('{{ $preset }}')" wire:loading.attr="disabled" wire:target="setPreset"
                    @class(['btn btn-sm', $timePreset === $preset ? 'btn-primary' : 'btn-outline-secondary'])>
                {{ strtoupper($preset) }}
            </button>
        @endforeach
        <span class="small text-muted align-self-center ms-2">{{ $dashboard['range']['from'] }} → {{ $dashboard['range']['to'] }}</span>
        <span class="small text-primary align-self-center ms-2" wire:loading wire:target="setPreset">Chargement…</span>
    </div>

    <div x-data="{
        tab: @js($activeTab),
        setTab(name) {
            this.tab = name;
            const url = new URL(window.location.href);
            url.searchParams.set('activeTab', name);
            history.replaceState({}, '', url);
        }
    }">
    <ul class="nav nav-tabs obiora-nav-tabs mb-3">
        @foreach(['overview' => 'Overview', 'cpu' => 'CPU', 'memory' => 'Memory', 'disk' => 'Disk', 'network' => 'Network', 'processes' => 'Processes'] as $tab => $label)
            <li class="nav-item">
                <button type="button" @click="setTab('{{ $tab }}')" :class="tab === '{{ $tab }}' ? 'nav-link active' : 'nav-link'">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if(!($dashboard['has_samples'] ?? false))
    <div class="alert alert-warning mb-4">
        <strong>Aucune métrique agent sur cette période.</strong>
        <span class="d-block small mt-1">Installez ou redémarrez l'agent métriques : <code>sudo systemctl restart obiora-agent</code> — ou via Doctor & Suite → Installation agent.</span>
        <a href="{{ route('doctor.index', ['server' => $server->id]) }}" class="btn btn-outline-warning btn-sm mt-2">Doctor & Suite</a>
    </div>
    @else
    <p class="small text-muted mb-3">{{ $dashboard['sample_count'] ?? 0 }} échantillons sur la période sélectionnée.</p>
    @endif

    <div x-show="tab === 'overview'" x-cloak>
    <div class="row g-3 mb-4">
        @foreach([
            ['id' => 'chart-cpu', 'title' => 'CPU %', 'key' => 'cpu', 'color' => '#3b82f6'],
            ['id' => 'chart-memory', 'title' => 'Memory %', 'key' => 'memory', 'color' => '#22c55e'],
            ['id' => 'chart-disk', 'title' => 'Disk %', 'key' => 'disk', 'color' => '#f59e0b'],
            ['id' => 'chart-load', 'title' => 'Load average', 'key' => 'load', 'multi' => true],
        ] as $chart)
        <div class="col-md-6">
            <div class="card obiora-card h-100">
                <div class="card-header d-flex justify-content-between">
                    <span>{{ $chart['title'] }}</span>
                    @if(empty($chart['multi']))
                        @php $s = $dashboard['series'][$chart['key']] ?? []; @endphp
                        <span class="small text-muted">avg {{ $s['avg'] ?? '—' }} · min {{ $s['min'] ?? '—' }} · max {{ $s['max'] ?? '—' }}</span>
                    @endif
                </div>
                <div class="card-body"><div id="{{ $chart['id'] }}" style="min-height:220px;"></div></div>
            </div>
        </div>
        @endforeach
    </div>
    </div>

    <div x-show="tab === 'cpu'" x-cloak>
        <div class="card obiora-card mb-4"><div class="card-body"><div id="chart-cpu-tab" style="min-height:280px;"></div></div></div>
        <div class="card obiora-card mb-4"><div class="card-body"><div id="chart-steal-tab" style="min-height:220px;"></div></div></div>
    </div>

    <div x-show="tab === 'memory'" x-cloak>
        <div class="card obiora-card mb-4"><div class="card-body"><div id="chart-memory-tab" style="min-height:280px;"></div></div></div>
        <div class="card obiora-card mb-4"><div class="card-body"><div id="chart-swap-tab" style="min-height:220px;"></div></div></div>
    </div>

    <div x-show="tab === 'disk'" x-cloak>
    <div class="card obiora-card mb-4"><div class="card-body"><div id="chart-disk-tab" style="min-height:280px;"></div></div></div>
    @if(count($dashboard['partitions']) > 0)
    <div class="card obiora-card">
        <div class="card-header">Partitions</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head"><tr><th>Mount</th><th>Usage</th></tr></thead>
                <tbody>
                    @foreach($dashboard['partitions'] as $part)
                    <tr>
                        <td>{{ $part['mount'] }}</td>
                        <td>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar" style="width: {{ min(100, $part['used_percent']) }}%"></div>
                            </div>
                            <span class="small">{{ $part['used_percent'] }}%</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    </div>

    <div x-show="tab === 'network'" x-cloak>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">TCP connexions (actuel)</div>
                    <div class="h4 mb-0">{{ $dashboard['stats']['tcp_connections'] ?? '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">RX moy.</div>
                    <div class="h4 mb-0">{{ $dashboard['network_series']['avg_rx'] ?? '—' }} kbps</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">TX moy.</div>
                    <div class="h4 mb-0">{{ $dashboard['network_series']['avg_tx'] ?? '—' }} kbps</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card obiora-card mb-4"><div class="card-header">Trafic réseau (RX/TX)</div><div class="card-body"><div id="chart-network-rxtx" style="min-height:280px;"></div></div></div>
    <div class="card obiora-card mb-4"><div class="card-header">Connexions TCP</div><div class="card-body"><div id="chart-network-tcp" style="min-height:220px;"></div></div></div>
    @if(count($dashboard['ip_addresses']) > 0)
    <div class="card obiora-card">
        <div class="card-header">Adresses IP</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head"><tr><th>Interface</th><th>Adresse</th></tr></thead>
                <tbody>
                    @foreach($dashboard['ip_addresses'] as $ip)
                    <tr><td>{{ $ip['iface'] }}</td><td class="font-monospace">{{ $ip['address'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    </div>

    <div x-show="tab === 'processes'" x-cloak>
    <div class="card obiora-card">
        <div class="card-header">Top processus (dernier snapshot)</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head"><tr><th>PID</th><th>Nom</th><th>CPU %</th><th>RAM %</th></tr></thead>
                <tbody>
                    @forelse($dashboard['processes'] as $proc)
                    <tr>
                        <td>{{ $proc['pid'] ?? '—' }}</td>
                        <td>{{ $proc['name'] ?? '—' }}</td>
                        <td>{{ $proc['cpu'] ?? '—' }}</td>
                        <td>{{ $proc['mem'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted small">Aucune donnée — installez l'agent métriques.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
    </div>

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }}</p>
    <div id="server-metrics-chart-data" class="d-none" data-chart='@json($chartPayload)'></div>
</div>
