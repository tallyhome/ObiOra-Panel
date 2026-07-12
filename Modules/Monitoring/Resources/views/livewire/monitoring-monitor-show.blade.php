<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $monitor->name }}</h1>
            <p class="text-muted small mb-0">
                {{ $monitor->type->label() }} — {{ $monitor->displayTarget() }}
                @if($monitor->last_status === 'up')
                    <span class="badge text-bg-success ms-1">Up</span>
                @elseif($monitor->last_status === 'down')
                    <span class="badge text-bg-danger ms-1">Down</span>
                @else
                    <span class="badge text-bg-secondary ms-1">Unknown</span>
                @endif
            </p>
        </div>
        <a href="{{ route('monitoring.monitors') }}" class="btn btn-outline-secondary btn-sm">← Moniteurs</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Uptime (30 j)</div>
                    <div class="h5 mb-0">{{ number_format($stats['uptime_percent'], 1) }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Réponse moy.</div>
                    <div class="h5 mb-0">{{ $stats['avg_ms'] !== null ? $stats['avg_ms'].' ms' : '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">DNS / TCP</div>
                    <div class="h6 mb-0">{{ $stats['avg_dns_ms'] ?? '—' }} / {{ $stats['avg_tcp_ms'] ?? '—' }} ms</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">TTFB moy.</div>
                    <div class="h5 mb-0">{{ $stats['avg_ttfb_ms'] !== null ? $stats['avg_ttfb_ms'].' ms' : '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Visites (30 j)</div>
                    <div class="h5 mb-0">{{ number_format($visitStats['total_30d']) }}</div>
                    <div class="small text-muted">{{ $visitStats['today'] }} aujourd'hui</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Dernière vérif.</div>
                    <div class="h6 mb-0">{{ \App\Support\UserTimezone::format($monitor->last_checked_at, 'd/m/Y H:i') ?: '—' }}</div>
                    <div class="small text-muted">{{ $monitor->last_response_ms ? $monitor->last_response_ms.' ms' : '' }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($embedSnippet)
    <div class="card obiora-card mb-4">
        <div class="card-header">Compteur de visites — snippet à intégrer sur le site</div>
        <div class="card-body">
            <p class="small text-muted mb-2">Collez ce script avant <code>&lt;/body&gt;</code> sur le site surveillé. Les visites sont comptées via pixel 1×1 (sans cookie).</p>
            <pre class="small bg-dark text-light p-2 rounded mb-0"><code>{{ $embedSnippet }}</code></pre>
        </div>
    </div>
    @endif

    <div class="card obiora-card mb-4">
        <div class="card-header">Temps de réponse</div>
        <div class="card-body"><div id="monitor-response-chart" style="min-height:240px;"></div></div>
    </div>

    <div class="card obiora-card">
        <div class="card-header">Derniers checks ({{ $stats['checks_total'] }} sur 30 j)</div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Statut</th>
                        <th>Réponse</th>
                        <th>HTTP</th>
                        <th>Date</th>
                        <th>Détail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentChecks as $check)
                    <tr>
                        <td>
                            @if($check['status'] === 'up')
                                <span class="badge text-bg-success">Up</span>
                            @else
                                <span class="badge text-bg-danger">Down</span>
                            @endif
                        </td>
                        <td>{{ $check['response_ms'] ? $check['response_ms'].' ms' : '—' }}</td>
                        <td class="small">{{ $check['http_code'] ?? '—' }}</td>
                        <td class="small text-nowrap">{{ $check['checked_at'] }}</td>
                        <td class="small text-muted">{{ $check['error'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted small">Aucun check enregistré — la sonde démarre au prochain cycle (1 min).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>
</div>

@script
<script>
    const monitorChart = @json($chartSeries);
    function renderMonitorChart() {
        const el = document.getElementById('monitor-response-chart');
        if (!el || typeof ApexCharts === 'undefined') return;
        el.innerHTML = '';
        new ApexCharts(el, {
            chart: { type: 'line', height: 240, toolbar: { show: false }, animations: { enabled: false } },
            series: [{ name: 'Response ms', data: monitorChart.values || [] }],
            xaxis: { categories: monitorChart.categories || [], labels: { show: false } },
            yaxis: { labels: { formatter: v => v + ' ms' } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#3b82f6'],
            dataLabels: { enabled: false },
        }).render();
    }
    document.addEventListener('livewire:navigated', renderMonitorChart);
    setTimeout(renderMonitorChart, 100);
</script>
@endscript
