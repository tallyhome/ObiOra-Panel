<div>
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
