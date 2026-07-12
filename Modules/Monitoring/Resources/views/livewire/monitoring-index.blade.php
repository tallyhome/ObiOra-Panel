<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="mb-3 pb-2">
        <h1 class="h5 text-muted mb-0">Flotte avancée — graphiques ping &amp; Doctor</h1>
    </div>

    @if(count($witnessSummary) > 0)
    <div class="card obiora-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>CrashHunter Witness</span>
            @if($witnessAnomalies > 0)
                <span class="badge text-bg-warning">{{ $witnessAnomalies }} anomalie(s)</span>
            @else
                <span class="badge text-bg-success">OK</span>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm obiora-table mb-0">
                <thead class="obiora-table-head">
                    <tr><th>Serveur</th><th>Ping</th><th>Witness</th><th>Dernière vue</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($witnessSummary as $row)
                    <tr @class(['table-warning' => $row['anomaly']])>
                        <td class="small fw-medium">{{ $row['server_name'] }}</td>
                        <td class="small">{{ $row['ping_ok'] ? 'OK' : 'KO' }}</td>
                        <td class="small">{{ $row['witness_status'] }}</td>
                        <td class="small text-nowrap">{{ $row['witness_last_at'] ?: '—' }}</td>
                        <td>
                            @if($row['anomaly'])
                                <span class="badge text-bg-warning">Ping OK / witness mort</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(!empty($initialFleet))
        <noscript>
            <div class="alert alert-info">Activez JavaScript pour le monitoring temps réel.</div>
        </noscript>
    @endif

    <div id="monitoring-app"
         data-fleet-url="{{ route('monitoring.api.fleet') }}"
         data-stream-url="{{ route('monitoring.api.stream') }}"
         data-ping-history-url="{{ url('/api/monitoring/servers') }}"
         data-score-history-url="{{ url('/api/monitoring/servers') }}"
         data-compare-base-url="{{ url('/api/monitoring/servers') }}"
         data-alerts-read-url="{{ url('/api/monitoring/alerts') }}"
         data-install-base-url="{{ url('/api/monitoring/servers') }}"
         data-diagnostics-latest-url="{{ url('/api/monitoring/servers') }}"
         data-doctor-url="{{ route('doctor.index') }}"
         data-panel-url="{{ $panelUrl }}"
         data-realtime-enabled="{{ $realtimeEnabled ? '1' : '0' }}"
         data-initial-fleet="{{ json_encode($initialFleet ?? []) }}"
         data-initial-alerts="{{ json_encode($initialAlerts ?? []) }}">
    </div>

    @vite(['resources/js/monitoring/main.js'])
</div>
