@php
    use App\Support\DashboardHealth;
    use App\Support\NetworkMetrics;
    $cpu = $metrics['cpu'] ?? [];
    $mem = $metrics['memory'] ?? [];
    $disk = $metrics['disk'] ?? [];
    $swap = $metrics['swap'] ?? [];
    $net = $network;
@endphp

<div @if($pollInterval > 0) wire:poll.{{ $pollInterval }}s="refresh" @endif>
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
        <div class="d-flex gap-2 align-items-center flex-wrap">
            @if ($realtimeEnabled && $pollInterval === 0)
                <span class="badge rounded-pill text-bg-primary" style="font-size: 0.7rem;" title="Mises à jour via WebSocket Reverb">
                    ⚡ Temps réel
                </span>
            @elseif ($realtimeEnabled && $pollInterval > 0)
                <span class="badge rounded-pill text-bg-primary" style="font-size: 0.7rem;" title="WebSocket Reverb + polling de secours">
                    ⚡ Reverb · {{ $pollInterval }}s
                </span>
            @elseif ($pollInterval > 0)
                <span class="badge rounded-pill" style="background: rgba(61,214,140,0.15); color: var(--obiora-primary); font-size: 0.7rem;">
                    <span class="obiora-status-dot ok me-1" style="width:6px;height:6px;"></span> Live · {{ $pollInterval }}s
                </span>
            @else
                <span class="badge rounded-pill text-bg-secondary" style="font-size: 0.7rem;">Manuel</span>
            @endif
            <select wire:model.live="pollInterval" class="form-select form-select-sm" style="width: auto; min-width: 9rem;" title="Rafraîchissement automatique">
                <option value="0">Auto : désactivé</option>
                <option value="3">Auto : 3 sec</option>
                <option value="5">Auto : 5 sec</option>
                <option value="10">Auto : 10 sec</option>
                <option value="30">Auto : 30 sec</option>
                <option value="60">Auto : 1 min</option>
            </select>
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
            <a href="{{ route('plugins.index') }}" class="obiora-quick-link"><span>📦</span> Marketplace</a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('services.index') }}" class="obiora-quick-link"><span>⚙️</span> Services</a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('websites.index') }}" class="obiora-quick-link"><span>🌐</span> Sites web</a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('docker.index') }}" class="obiora-quick-link"><span>🐳</span> Docker</a>
        </div>
    </div>

    <div class="row g-3">
        {{-- Colonne gauche : graphiques --}}
        <div class="col-lg-8">
            {{-- Bandwidth temps réel --}}
            <div class="card obiora-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Bande passante</span>
                    <div class="d-flex gap-3 small">
                        <span><span class="obiora-net-legend down"></span> Download <strong id="net-rx-live" class="obiora-net-down">{{ $net['rx_rate_human'] ?? '0 B/s' }}</strong></span>
                        <span><span class="obiora-net-legend up"></span> Upload <strong id="net-tx-live" class="obiora-net-up">{{ $net['tx_rate_human'] ?? '0 B/s' }}</strong></span>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div id="bandwidth-chart" wire:ignore style="min-height: 220px;"></div>
                </div>
            </div>

            {{-- Charge CPU --}}
            <div class="card obiora-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Charge CPU</span>
                    <span class="small text-muted">
                        Load {{ $cpu['load_1'] ?? 0 }} · {{ $cpu['load_5'] ?? 0 }} · {{ $cpu['load_15'] ?? 0 }}
                        · {{ $cpu['cores'] ?? 0 }} cœurs
                    </span>
                </div>
                <div class="card-body pt-2">
                    <div id="cpu-chart" wire:ignore style="min-height: 200px;"></div>
                </div>
            </div>

            {{-- Services --}}
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
                                        <td colspan="4" class="text-center text-muted py-4">Aucun service panel détecté sur ce serveur.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Colonne droite : widgets empilés --}}
        <div class="col-lg-4">
            {{-- En un coup d'œil --}}
            <div class="card obiora-card mb-3">
                <div class="card-header">En un coup d'œil</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <div class="obiora-glance-item">
                            <span class="obiora-status-dot {{ $glance['load'] ?? 'ok' }}"></span> Charge
                        </div>
                        <div class="obiora-glance-item">
                            <span class="obiora-status-dot {{ $glance['disk'] ?? 'ok' }}"></span> Disque
                        </div>
                        <div class="obiora-glance-item">
                            <span class="obiora-status-dot {{ $glance['memory'] ?? 'ok' }}"></span> RAM
                        </div>
                    </div>
                    <div class="obiora-widget-title">Uptime</div>
                    <div class="obiora-uptime mb-1">{{ $metrics['uptime'] ?? 'N/A' }}</div>
                    <div class="small text-muted">depuis le dernier démarrage</div>
                    <hr class="border-secondary my-3 opacity-25">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Charge relative</span>
                        <span>{{ $glance['load_pct'] ?? 0 }}%</span>
                    </div>
                </div>
            </div>

            {{-- RAM --}}
            <div class="card obiora-card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <svg class="obiora-metric-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M7 10h2M11 10h2M15 10h2M7 14h2M11 14h2M15 14h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Mémoire RAM
                </div>
                <div class="card-body">
                    <div class="obiora-metric-widget">
                        <div class="obiora-metric-visual" aria-hidden="true">
                            <svg viewBox="0 0 64 64" fill="none">
                                <rect x="8" y="18" width="48" height="28" rx="4" stroke="#3dd68c" stroke-width="2"/>
                                <rect x="14" y="24" width="8" height="16" rx="1" fill="#3dd68c" opacity="0.85"/>
                                <rect x="26" y="24" width="8" height="16" rx="1" fill="#3dd68c" opacity="0.55"/>
                                <rect x="38" y="24" width="8" height="16" rx="1" fill="#3dd68c" opacity="0.35"/>
                                <rect x="50" y="24" width="2" height="16" rx="1" fill="#6366f1" opacity="0.6"/>
                            </svg>
                        </div>
                        <div class="obiora-metric-data flex-grow-1">
                            <div class="obiora-metric-row">
                                <div class="obiora-metric-cell">
                                    <span class="value">{{ DashboardHealth::formatBytesFr($mem['used'] ?? 0) }}</span>
                                    <span class="label">utilisé</span>
                                </div>
                                <div class="obiora-metric-cell">
                                    <span class="value">{{ DashboardHealth::formatBytesFr($mem['free'] ?? 0) }}</span>
                                    <span class="label">libre</span>
                                </div>
                                <div class="obiora-metric-cell">
                                    <span class="value">{{ DashboardHealth::formatBytesFr($mem['total'] ?? 0) }}</span>
                                    <span class="label">total</span>
                                </div>
                            </div>
                            <div class="obiora-progress {{ $glance['memory'] ?? 'ok' }} mb-2">
                                <div class="bar" style="width: {{ min(100, $mem['percent'] ?? 0) }}%"></div>
                            </div>
                            <div class="small text-muted">RAM utilisée à {{ $mem['percent'] ?? 0 }}%</div>
                        </div>
                    </div>
                    @if (($swap['total'] ?? 0) > 0)
                        <hr class="border-secondary my-2 opacity-25">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Swap</span>
                            <span>{{ $swap['percent'] ?? 0 }}%</span>
                        </div>
                        <div class="obiora-progress {{ ($swap['percent'] ?? 0) > 50 ? 'warning' : 'ok' }}">
                            <div class="bar" style="width: {{ min(100, $swap['percent'] ?? 0) }}%"></div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Disque --}}
            <div class="card obiora-card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <svg class="obiora-metric-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <ellipse cx="12" cy="6" rx="8" ry="3" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M4 6v12c0 1.66 3.58 3 8 3s8-1.34 8-3V6" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    Quota disque
                </div>
                <div class="card-body">
                    <div class="obiora-metric-widget">
                        <div class="obiora-metric-visual" aria-hidden="true">
                            <svg viewBox="0 0 64 64" fill="none">
                                <ellipse cx="32" cy="16" rx="22" ry="8" stroke="#3dd68c" stroke-width="2"/>
                                <path d="M10 16v28c0 4.4 9.85 8 22 8s22-3.6 22-8V16" stroke="#3dd68c" stroke-width="2"/>
                                <path d="M10 30c0 4.4 9.85 8 22 8s22-3.6 22-8" stroke="#6366f1" stroke-width="1.5" opacity="0.7"/>
                                <circle cx="32" cy="30" r="6" fill="#3dd68c" opacity="0.25"/>
                            </svg>
                        </div>
                        <div class="obiora-metric-data flex-grow-1">
                            <div class="obiora-metric-row">
                                <div class="obiora-metric-cell">
                                    <span class="value">{{ DashboardHealth::formatBytesFr($disk['used'] ?? 0) }}</span>
                                    <span class="label">utilisé</span>
                                </div>
                                <div class="obiora-metric-cell">
                                    <span class="value">{{ DashboardHealth::formatBytesFr($disk['free'] ?? 0) }}</span>
                                    <span class="label">libre</span>
                                </div>
                                <div class="obiora-metric-cell">
                                    <span class="value">{{ DashboardHealth::formatBytesFr($disk['total'] ?? 0) }}</span>
                                    <span class="label">total</span>
                                </div>
                            </div>
                            <div class="obiora-progress {{ $glance['disk'] ?? 'ok' }} mb-2">
                                <div class="bar" style="width: {{ min(100, $disk['percent'] ?? 0) }}%"></div>
                            </div>
                            <div class="small text-muted">Espace utilisé à {{ $disk['percent'] ?? 0 }}% (<code>/</code>)</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Network Info --}}
            <div class="card obiora-card">
                <div class="card-header">Network Info</div>
                <div class="card-body p-0">
                    <div class="px-3 py-2 border-bottom obiora-net-border">
                        <div class="obiora-widget-title mb-2">Interface</div>
                        <div class="d-flex justify-content-between align-items-center small">
                            <code class="text-info">{{ $net['interface'] ?? '—' }}</code>
                            <div class="text-end">
                                <div><span class="obiora-net-down">↓ {{ $net['rx_rate_human'] ?? '0 B/s' }}</span></div>
                                <div><span class="obiora-net-up">↑ {{ $net['tx_rate_human'] ?? '0 B/s' }}</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="px-3 py-2 border-bottom obiora-net-border">
                        <div class="obiora-widget-title mb-2">Span</div>
                        <table class="table table-sm obiora-net-table mb-0">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th class="text-end obiora-net-down">Download</th>
                                    <th class="text-end obiora-net-up">Upload</th>
                                </tr>
                            </thead>
                            <tbody id="net-span-body">
                                @forelse ($net['span'] ?? [] as $row)
                                    <tr>
                                        <td class="text-muted">{{ $row['label'] }}</td>
                                        <td class="text-end obiora-net-down font-monospace">{{ NetworkMetrics::formatTotal($row['rx']) }}</td>
                                        <td class="text-end obiora-net-up font-monospace">{{ NetworkMetrics::formatTotal($row['tx']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted small">Données en cours de collecte…</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (! empty($net['daily']))
                        <div class="px-3 py-2">
                            <div class="obiora-widget-title mb-2">Date</div>
                            <table class="table table-sm obiora-net-table mb-0">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th class="text-end obiora-net-down">Download</th>
                                        <th class="text-end obiora-net-up">Upload</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($net['daily'] as $day)
                                        <tr>
                                            <td class="text-muted">{{ $day['date'] }}</td>
                                            <td class="text-end obiora-net-down font-monospace">{{ NetworkMetrics::formatTotal($day['rx']) }}</td>
                                            <td class="text-end obiora-net-up font-monospace">{{ NetworkMetrics::formatTotal($day['tx']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    let cpuChart, bwChart;
    let bwPollTimer = null;
    const BW_MAX = 48;
    let bwLabels = [];
    let bwDown = [];
    let bwUp = [];

    const chartTheme = {
        foreColor: '#8b8ba3',
        gridColor: '#2d2d42',
        primary: '#3dd68c',
        download: '#3b82f6',
        upload: '#3dd68c',
    };

    function kbFromRate(bytesPerSec) {
        return Math.round((bytesPerSec / 1024) * 100) / 100;
    }

    function pushBwPoint(rx, tx) {
        const label = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        bwLabels.push(label);
        bwDown.push(kbFromRate(rx));
        bwUp.push(kbFromRate(tx));
        if (bwLabels.length > BW_MAX) {
            bwLabels.shift();
            bwDown.shift();
            bwUp.shift();
        }
    }

    function initBandwidthChart() {
        if (!window.ApexCharts) return;
        const el = document.querySelector('#bandwidth-chart');
        if (!el) return;

        const opts = {
            chart: {
                type: 'area',
                height: 220,
                toolbar: { show: false },
                background: 'transparent',
                animations: { enabled: true, easing: 'linear', dynamicAnimation: { speed: 400 } },
            },
            theme: { mode: 'dark' },
            series: [
                { name: 'Download', data: [] },
                { name: 'Upload', data: [] },
            ],
            colors: [chartTheme.download, chartTheme.upload],
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 },
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: chartTheme.foreColor }, rotate: 0, hideOverlappingLabels: true },
                tickAmount: 6,
            },
            yaxis: {
                labels: {
                    style: { colors: chartTheme.foreColor },
                    formatter: (v) => v >= 1024 ? (v / 1024).toFixed(1) + ' MB/s' : v.toFixed(0) + ' KB/s',
                },
                min: 0,
            },
            grid: { borderColor: chartTheme.gridColor },
            legend: { labels: { colors: chartTheme.foreColor }, position: 'top', horizontalAlign: 'right' },
            tooltip: {
                y: { formatter: (v) => v >= 1024 ? (v / 1024).toFixed(2) + ' MB/s' : v.toFixed(1) + ' KB/s' },
            },
        };

        if (bwChart) bwChart.destroy();
        bwChart = new ApexCharts(el, opts);
        bwChart.render();
    }

    function updateBandwidthChart() {
        if (!bwChart) return;
        bwChart.updateOptions({ xaxis: { categories: bwLabels } });
        bwChart.updateSeries([
            { name: 'Download', data: bwDown },
            { name: 'Upload', data: bwUp },
        ]);
    }

    function renderCpuChart(metrics) {
        if (!window.ApexCharts) return;
        const cpuEl = document.querySelector('#cpu-chart');
        if (!cpuEl) return;

        const opts = {
            chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
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
            plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
            dataLabels: { enabled: true, style: { colors: [chartTheme.foreColor] } },
        };

        if (cpuChart) cpuChart.destroy();
        cpuChart = new ApexCharts(cpuEl, opts);
        cpuChart.render();
    }

    async function pollNetwork() {
        try {
            const data = await $wire.networkRates();
            pushBwPoint(data.rx_rate || 0, data.tx_rate || 0);
            updateBandwidthChart();

            const rxEl = document.getElementById('net-rx-live');
            const txEl = document.getElementById('net-tx-live');
            if (rxEl) rxEl.textContent = data.rx_rate_human || '0 B/s';
            if (txEl) txEl.textContent = data.tx_rate_human || '0 B/s';
        } catch (e) {
            // silencieux si hors ligne
        }
    }

    function startBwPoll(intervalSec) {
        if (bwPollTimer) clearInterval(bwPollTimer);
        pollNetwork();
        const sec = Number(intervalSec) || 0;
        if (sec > 0) {
            bwPollTimer = setInterval(pollNetwork, sec * 1000);
        }
    }

    $wire.on('server-changed', () => {
        bwLabels = [];
        bwDown = [];
        bwUp = [];
        $wire.refresh();
        startBwPoll($wire.pollInterval > 0 ? $wire.pollInterval : 0);
    });

    $wire.on('dashboard-poll-changed', ({ interval }) => {
        startBwPoll(interval > 0 ? interval : 0);
    });

    $wire.on('dashboard-refreshed', ({ metrics }) => {
        if (metrics) renderCpuChart(metrics);
    });

    initBandwidthChart();
    renderCpuChart(@js($metrics));
    startBwPoll(@js($pollInterval) > 0 ? @js($pollInterval) : 0);

    Livewire.hook('morph.updated', () => {
        const metrics = $wire.get('metrics');
        if (metrics && metrics.cpu) renderCpuChart(metrics);
    });
</script>
@endscript
