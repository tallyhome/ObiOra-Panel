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
        @if($witnessAnomalies > 0)
        <div class="card-body border-bottom py-3">
            <p class="small fw-medium mb-2">Ping OK mais witness mort — que faire ?</p>
            <ol class="small text-muted mb-2 ps-3">
                <li>Sur le serveur concerné (SSH root) : <code class="text-break">systemctl status crashhunter</code></li>
                <li>Redémarrer : <code>sudo systemctl restart crashhunter</code></li>
                <li>Si absent : <strong>Doctor & Suite</strong> → déployer/réinstaller CrashHunter sur le serveur</li>
                <li>Cas typique : kernel ou agent gelé alors que ICMP répond encore — consultez Crash Analyzer / Doctor</li>
            </ol>
            @foreach($witnessSummary as $row)
                @if($row['anomaly'])
                <a href="{{ route('doctor.index', ['server' => $row['server_id']]) }}" class="btn btn-outline-warning btn-sm me-1">
                    Doctor — {{ $row['server_name'] }}
                </a>
                <a href="{{ route('monitoring.servers.show', $row['server_id']) }}" class="btn btn-outline-secondary btn-sm me-1">Fiche serveur</a>
                @endif
            @endforeach
        </div>
        @elseif(($witnessStaleCount ?? 0) > 0)
        <div class="card-body border-bottom py-3">
            <p class="small fw-medium mb-2">Witness inactif ou « dead » — que faire ?</p>
            <ol class="small text-muted mb-2 ps-3">
                <li>Vérifier le service : <code>systemctl status crashhunter</code> puis <code>sudo systemctl restart crashhunter</code></li>
                <li>Confirmer <code>witness.enabled: true</code> dans <code>/opt/crashhunter/config.yaml</code></li>
                <li>Réinstaller via <strong>Doctor & Suite</strong> si le binaire ou la config manque</li>
                <li>Rapport Black Box : <strong>Doctor & Suite</strong> → section « CrashHunter Enterprise — Black Box &amp; Witness »</li>
            </ol>
            @foreach($witnessSummary as $row)
                @if(!empty($row['remediation']))
                <a href="{{ route('doctor.index', ['server' => $row['server_id']]) }}#crash-hunter-black-box" class="btn btn-outline-warning btn-sm me-1 mb-1">
                    Black Box — {{ $row['server_name'] }}
                </a>
                @endif
            @endforeach
        </div>
        @endif
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
                        <td class="small">
                            @if($row['witness_status'] === 'not_installed')
                                <span class="text-muted">non installé</span>
                            @else
                                {{ $row['witness_status'] }}
                            @endif
                        </td>
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
