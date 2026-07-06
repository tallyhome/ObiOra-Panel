<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Serveur actif : <strong>{{ $serverName }}</strong></p>
        </div>
        <button wire:click="refresh" class="btn btn-outline-primary btn-sm" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="refresh">Actualiser</span>
            <span wire:loading wire:target="refresh">...</span>
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="text-muted small">CPU (load 1m)</div>
                    <div class="fs-3 fw-bold">{{ $metrics['cpu']['load_1'] ?? 0 }}</div>
                    <div class="small text-muted">{{ $metrics['cpu']['cores'] ?? 0 }} cœurs</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="text-muted small">RAM</div>
                    <div class="fs-3 fw-bold">{{ $metrics['memory']['percent'] ?? 0 }}%</div>
                    <div class="small text-muted">{{ number_format(($metrics['memory']['used'] ?? 0) / 1073741824, 1) }} / {{ number_format(($metrics['memory']['total'] ?? 0) / 1073741824, 1) }} Go</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Disque</div>
                    <div class="fs-3 fw-bold">{{ $metrics['disk']['percent'] ?? 0 }}%</div>
                    <div class="small text-muted">{{ number_format(($metrics['disk']['used'] ?? 0) / 1073741824, 1) }} / {{ number_format(($metrics['disk']['total'] ?? 0) / 1073741824, 1) }} Go</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Uptime</div>
                    <div class="fs-4 fw-bold">{{ $metrics['uptime'] ?? 'N/A' }}</div>
                    <div class="small text-muted">{{ $metrics['hostname'] ?? '' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card obiora-card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Charge CPU</h2>
                    <div id="cpu-chart" wire:ignore style="min-height: 280px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card obiora-card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Mémoire & Swap</h2>
                    <div id="memory-chart" wire:ignore style="min-height: 200px;"></div>
                </div>
            </div>
            <div class="card obiora-card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Système</h2>
                    <dl class="row small mb-0">
                        <dt class="col-5">OS</dt>
                        <dd class="col-7">{{ $metrics['os'] ?? 'N/A' }}</dd>
                        <dt class="col-5">Load 5m</dt>
                        <dd class="col-7">{{ $metrics['cpu']['load_5'] ?? 0 }}</dd>
                        <dt class="col-5">Load 15m</dt>
                        <dd class="col-7">{{ $metrics['cpu']['load_15'] ?? 0 }}</dd>
                        <dt class="col-5">Swap</dt>
                        <dd class="col-7">{{ $metrics['swap']['percent'] ?? 0 }}%</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    let cpuChart, memChart;

    function renderCharts(metrics) {
        if (!window.ApexCharts) return;

        const cpuOpts = {
            chart: { type: 'bar', height: 260, toolbar: { show: false } },
            series: [{ name: 'Load', data: [
                metrics.cpu?.load_1 || 0,
                metrics.cpu?.load_5 || 0,
                metrics.cpu?.load_15 || 0,
            ]}],
            xaxis: { categories: ['1 min', '5 min', '15 min'] },
            colors: ['#4f46e5'],
        };

        const memOpts = {
            chart: { type: 'donut', height: 200 },
            series: [
                metrics.memory?.used || 0,
                metrics.memory?.free || 0,
                metrics.swap?.used || 0,
            ],
            labels: ['RAM utilisée', 'RAM libre', 'Swap'],
            colors: ['#4f46e5', '#22c55e', '#f59e0b'],
        };

        if (cpuChart) { cpuChart.destroy(); }
        if (memChart) { memChart.destroy(); }

        cpuChart = new ApexCharts(document.querySelector('#cpu-chart'), cpuOpts);
        memChart = new ApexCharts(document.querySelector('#memory-chart'), memOpts);
        cpuChart.render();
        memChart.render();
    }

    $wire.on('server-changed', () => $wire.refresh());
    renderCharts(@js($metrics));

    Livewire.hook('morph.updated', () => {
        renderCharts(@js($metrics));
    });
</script>
@endscript
