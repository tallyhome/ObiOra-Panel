<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
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
                <span class="badge text-bg-dark ms-1">Check {{ $monitor->intervalLabel() }}</span>
                @if($monitor->last_response_ms)
                    <span class="badge text-bg-info ms-1">{{ $monitor->last_response_ms }} ms</span>
                @endif
            </p>
            <p class="small text-muted mb-0">
                Uptime 24h :
                <span @class(['fw-semibold', 'text-success' => $stats24h['uptime_percent'] >= 99, 'text-warning' => $stats24h['uptime_percent'] < 99 && $stats24h['uptime_percent'] >= 90, 'text-danger' => $stats24h['uptime_percent'] < 90])>
                    {{ number_format($stats24h['uptime_percent'], 2) }}%
                </span>
                · Dernière vérif. {{ \App\Support\UserTimezone::format($monitor->last_checked_at, 'd/m/Y H:i:s') ?: '—' }}
            </p>
        </div>
        <a href="{{ route('monitoring.monitors') }}" class="btn btn-outline-secondary btn-sm">← Moniteurs</a>
    </div>

    <div class="d-flex flex-wrap gap-1 mb-3" wire:loading.class="opacity-50" wire:target="setPreset">
        @foreach($presets as $preset)
            <button type="button" wire:click="setPreset('{{ $preset }}')" wire:loading.attr="disabled" wire:target="setPreset"
                    @class(['btn btn-sm', $timePreset === $preset ? 'btn-primary' : 'btn-outline-secondary'])>
                {{ strtoupper($preset) }}
            </button>
        @endforeach
        <span class="small text-muted align-self-center ms-2">{{ $rangeLabel }}</span>
        <span class="small text-primary align-self-center ms-2" wire:loading wire:target="setPreset">Chargement…</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <div class="small text-muted">Uptime (période)</div>
                    <div @class(['h5 mb-0', 'text-success' => $stats['uptime_percent'] >= 99, 'text-warning' => $stats['uptime_percent'] < 99 && $stats['uptime_percent'] >= 90, 'text-danger' => $stats['uptime_percent'] < 90])>
                        {{ number_format($stats['uptime_percent'], 2) }}%
                    </div>
                    <div class="small text-muted">{{ $stats['checks_total'] }} checks</div>
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
                    <div class="small text-muted">Min / Max</div>
                    <div class="h6 mb-0">
                        <span class="text-success">{{ $stats['min_ms'] ?? '—' }}</span>
                        /
                        <span class="text-warning">{{ $stats['max_ms'] ?? '—' }}</span>
                        @if($stats['min_ms'] !== null) ms @endif
                    </div>
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Temps de réponse</span>
            @if($stats['avg_ms'] !== null)
                <span class="small text-muted">avg {{ $stats['avg_ms'] }} ms · min {{ $stats['min_ms'] }} · max {{ $stats['max_ms'] }}</span>
            @endif
        </div>
        <div class="card-body"><div id="monitor-response-chart" data-chart='@json($chartSeries)' style="min-height:260px;"></div></div>
    </div>

    <div class="card obiora-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Statut Up / Down</span>
            <span class="small text-muted">
                <span class="text-success">■</span> Up
                <span class="text-danger ms-2">■</span> Down
                <span class="text-secondary ms-2">■</span> Pas de données
            </span>
        </div>
        <div class="card-body py-3">
            <div class="d-flex obiora-status-timeline" style="height:28px; gap:2px;">
                @foreach($statusTimeline as $segment)
                    <div class="flex-fill rounded-1" style="background:{{ $segment['color'] }}; min-width:3px;" title="{{ $segment['title'] }}"></div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card obiora-card">
        <div class="card-body py-3">
            <button type="button" wire:click="$toggle('recentChecksOpen')" class="btn btn-link btn-sm text-start p-0 text-decoration-none w-100">
                <span class="fw-medium">Derniers checks</span>
                <span class="text-muted ms-1">
                    ({{ count($recentChecks) }} / {{ $recentChecksLimit }} entrées — cliquez pour {{ $recentChecksOpen ? 'masquer' : 'afficher' }} le détail)
                </span>
                <span class="ms-1" aria-hidden="true">{{ $recentChecksOpen ? '▾' : '▸' }}</span>
            </button>
            @if($recentChecksOpen)
            <p class="small text-muted mt-2 mb-0">Historique détaillé des {{ $recentChecksLimit }} dernières sondes (les plus anciennes sortent automatiquement de cette liste).</p>
            <div class="table-responsive mt-2">
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
            @endif
        </div>
    </div>

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>
</div>
