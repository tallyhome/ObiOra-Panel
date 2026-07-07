@php
    use App\Support\DashboardHealth;
    $cpu = $metrics['cpu'] ?? [];
    $mem = $metrics['memory'] ?? [];
    $disk = $metrics['disk'] ?? [];
    $swap = $metrics['swap'] ?? [];
@endphp

<div wire:poll.10s="refresh">
    {{-- En-tête --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Vue d'ensemble</h1>
            <p class="text-muted mb-0 small">
                <span class="text-success">●</span> {{ $serverName }}
                · {{ $metrics['hostname'] ?? '—' }}
                · {{ $metrics['os'] ?? 'Linux' }}
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge rounded-pill" style="background: rgba(61,214,140,0.15); color: var(--obiora-primary); font-size: 0.7rem;">
                <span class="obiora-status-dot ok me-1" style="width:6px;height:6px;"></span> Live · 10s
            </span>
            <button wire:click="refresh" class="btn btn-outline-primary btn-sm" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refresh">↻ Actualiser</span>
                <span wire:loading wire:target="refresh">...</span>
            </button>
            <a href="{{ route('services.index') }}" class="btn btn-primary btn-sm">Services</a>
        </div>
    </div>

    {{-- Raccourcis seedbox --}}
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-3">
            <a href="{{ route('plugins.index') }}" class="obiora-quick-link">
                <span>📦</span> Marketplace
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('services.index') }}" class="obiora-quick-link">
                <span>⚙️</span> Services
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('websites.index') }}" class="obiora-quick-link">
                <span>🌐</span> Sites web
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('docker.index') }}" class="obiora-quick-link">
                <span>🐳</span> Docker
            </a>
        </div>
    </div>

    <div class="row g-3">
        {{-- At a Glance (Swizzin) --}}
        <div class="col-lg-4">
            <div class="card obiora-card h-100">
                <div class="card-header">En un coup d'œil</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-4 mb-4">
                        <div class="obiora-glance-item">
                            <span class="obiora-status-dot {{ $glance['load'] ?? 'ok' }}"></span>
                            Charge
                        </div>
                        <div class="obiora-glance-item">
                            <span class="obiora-status-dot {{ $glance['disk'] ?? 'ok' }}"></span>
                            Disque
                        </div>
                        <div class="obiora-glance-item">
                            <span class="obiora-status-dot {{ $glance['memory'] ?? 'ok' }}"></span>
                            RAM
                        </div>
                    </div>

                    <div class="obiora-widget-title">Uptime</div>
                    <div class="obiora-uptime mb-1">{{ $metrics['uptime'] ?? 'N/A' }}</div>
                    <div class="small text-muted">depuis le dernier démarrage</div>

                    <hr class="border-secondary my-3 opacity-25">

                    <div class="obiora-widget-title">Charge système</div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Load 1m / 5m / 15m</span>
                        <span class="text-muted">{{ $cpu['load_1'] ?? 0 }} · {{ $cpu['load_5'] ?? 0 }} · {{ $cpu['load_15'] ?? 0 }}</span>
                    </div>
                    <div class="small text-muted">{{ $cpu['cores'] ?? 0 }} cœurs · {{ $glance['load_pct'] ?? 0 }}% charge relative</div>
                </div>
            </div>
        </div>

        {{-- RAM --}}
        <div class="col-lg-4">
            <div class="card obiora-card h-100">
                <div class="card-header">Mémoire RAM</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <div>
                            <div class="fs-4 fw-bold">{{ $mem['percent'] ?? 0 }}%</div>
                            <div class="small text-muted">utilisée</div>
                        </div>
                        <div class="text-end small text-muted">
                            {{ DashboardHealth::formatBytes($mem['used'] ?? 0) }}
                            <br>/ {{ DashboardHealth::formatBytes($mem['total'] ?? 0) }}
                        </div>
                    </div>
                    <div class="obiora-progress {{ $glance['memory'] ?? 'ok' }} mb-3">
                        <div class="bar" style="width: {{ min(100, $mem['percent'] ?? 0) }}%"></div>
                    </div>

                    <div class="obiora-widget-title mt-3">Swap</div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $swap['percent'] ?? 0 }}%</span>
                        <span class="text-muted">{{ DashboardHealth::formatBytes($swap['used'] ?? 0) }} / {{ DashboardHealth::formatBytes($swap['total'] ?? 0) }}</span>
                    </div>
                    <div class="obiora-progress {{ ($swap['percent'] ?? 0) > 50 ? 'warning' : 'ok' }}">
                        <div class="bar" style="width: {{ min(100, $swap['percent'] ?? 0) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Disque --}}
        <div class="col-lg-4">
            <div class="card obiora-card h-100">
                <div class="card-header">Quota disque</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <div>
                            <div class="fs-4 fw-bold">{{ $disk['percent'] ?? 0 }}%</div>
                            <div class="small text-muted">utilisé · <code class="text-muted">/</code></div>
                        </div>
                        <div class="text-end small text-muted">
                            {{ DashboardHealth::formatBytes($disk['used'] ?? 0) }}
                            <br>/ {{ DashboardHealth::formatBytes($disk['total'] ?? 0) }}
                        </div>
                    </div>
                    <div class="obiora-progress {{ $glance['disk'] ?? 'ok' }} mb-3">
                        <div class="bar" style="width: {{ min(100, $disk['percent'] ?? 0) }}%"></div>
                    </div>
                    <div class="small text-muted">
                        {{ DashboardHealth::formatBytes($disk['free'] ?? 0) }} libres
                    </div>

                    <hr class="border-secondary my-3 opacity-25">

                    <div class="obiora-widget-title">Système</div>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">OS</dt>
                        <dd class="col-7 mb-1">{{ $metrics['os'] ?? 'N/A' }}</dd>
                        <dt class="col-5 text-muted">Hostname</dt>
                        <dd class="col-7 mb-0">{{ $metrics['hostname'] ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Graphiques --}}
        <div class="col-lg-7">
            <div class="card obiora-card">
                <div class="card-header">Charge CPU</div>
                <div class="card-body">
                    <div id="cpu-chart" wire:ignore style="min-height: 260px;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card obiora-card h-100">
                <div class="card-header">Répartition mémoire</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div id="memory-chart" wire:ignore style="min-height: 260px; width: 100%;"></div>
                </div>
            </div>
        </div>

        {{-- Services (Swizzin Service Info) --}}
        <div class="col-12">
            <div class="card obiora-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Services clés</span>
                    <a href="{{ route('services.index') }}" class="small text-decoration-none" style="color: var(--obiora-primary)">Voir tout →</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table obiora-table mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Service</th>
                                    <th>État</th>
                                    <th>Description</th>
                                    <th class="pe-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($services as $svc)
                                    <tr>
                                        <td class="ps-3 fw-medium">{{ $svc['name'] }}</td>
                                        <td>
                                            @if ($svc['active'] === 'active')
                                                <span class="obiora-svc-badge active">actif</span>
                                            @else
                                                <span class="obiora-svc-badge inactive">{{ $svc['active'] }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ Str::limit($svc['description'], 60) }}</td>
                                        <td class="pe-3 text-end">
                                            <a href="{{ route('services.index') }}" class="btn btn-outline-primary btn-sm py-0 px-2">Gérer</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Aucun service panel détecté sur ce serveur.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    let cpuChart, memChart;

    const chartTheme = {
        foreColor: '#8b8ba3',
        gridColor: '#2d2d42',
        primary: '#3dd68c',
        secondary: '#6366f1',
        warning: '#fbbf24',
    };

    function renderCharts(metrics) {
        if (!window.ApexCharts) return;

        const cpuOpts = {
            chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent' },
            theme: { mode: 'dark' },
            series: [{ name: 'Load average', data: [
                metrics.cpu?.load_1 || 0,
                metrics.cpu?.load_5 || 0,
                metrics.cpu?.load_15 || 0,
            ]}],
            xaxis: {
                categories: ['1 min', '5 min', '15 min'],
                labels: { style: { colors: chartTheme.foreColor } },
            },
            yaxis: { labels: { style: { colors: chartTheme.foreColor } } },
            grid: { borderColor: chartTheme.gridColor },
            colors: [chartTheme.primary],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 } },
            stroke: { curve: 'smooth', width: 2 },
            dataLabels: { enabled: false },
        };

        const memOpts = {
            chart: { type: 'donut', height: 260, background: 'transparent' },
            theme: { mode: 'dark' },
            series: [
                metrics.memory?.used || 0,
                metrics.memory?.free || 0,
                metrics.swap?.used || 0,
            ],
            labels: ['RAM utilisée', 'RAM libre', 'Swap'],
            colors: [chartTheme.primary, chartTheme.secondary, chartTheme.warning],
            legend: { position: 'bottom', labels: { colors: chartTheme.foreColor } },
            plotOptions: { pie: { donut: { size: '65%' } } },
            dataLabels: { enabled: false },
        };

        if (cpuChart) cpuChart.destroy();
        if (memChart) memChart.destroy();

        const cpuEl = document.querySelector('#cpu-chart');
        const memEl = document.querySelector('#memory-chart');
        if (cpuEl) { cpuChart = new ApexCharts(cpuEl, cpuOpts); cpuChart.render(); }
        if (memEl) { memChart = new ApexCharts(memEl, memOpts); memChart.render(); }
    }

    $wire.on('server-changed', () => $wire.refresh());
    renderCharts(@js($metrics));

    Livewire.hook('morph.updated', () => {
        renderCharts(@js($metrics));
    });
</script>
@endscript
