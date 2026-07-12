<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Doctor &amp; Suite — {{ $server->name }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; line-height: 1.45; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        .hero { background: linear-gradient(135deg, #1e3a5f, #0f766e); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
        .hero h1 { margin: 0 0 .35rem; font-size: 1.45rem; }
        .hero p { margin: .2rem 0; opacity: .95; font-size: .92rem; }
        .card { background: #1e293b; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .card h2 { margin: 0 0 .75rem; font-size: 1rem; color: #93c5fd; }
        .card h3 { margin: 1rem 0 .5rem; font-size: .9rem; color: #cbd5e1; }
        table { border-collapse: collapse; width: 100%; font-size: .82rem; margin-top: .5rem; }
        th, td { border: 1px solid #334155; padding: 7px 8px; text-align: left; vertical-align: top; }
        th { background: #334155; }
        .badge { display: inline-block; padding: .12rem .45rem; border-radius: 4px; font-size: .72rem; }
        .badge-ok { background: #166534; }
        .badge-warn { background: #92400e; }
        .badge-danger { background: #991b1b; }
        pre { background: #0b1220; padding: .75rem; border-radius: 8px; overflow: auto; font-size: .75rem; white-space: pre-wrap; word-break: break-word; max-height: 320px; }
        dl { display: grid; grid-template-columns: 180px 1fr; gap: .35rem .75rem; font-size: .88rem; margin: 0; }
        dt { color: #94a3b8; }
        dd { margin: 0; }
        .muted { color: #94a3b8; font-size: .85rem; }
        @media print { body { background: #fff; color: #111; } .card, .hero { background: #f8fafc; color: #111; } th { background: #e2e8f0; } }
    </style>
</head>
<body>
@php
    $plain = $payload['plain_summary'] ?? null;
    $doctor = $payload['doctor']['latest_report'] ?? null;
    $ca = $payload['crash_analyzer'] ?? [];
    $ch = $payload['crash_hunter'] ?? [];
    $agents = $payload['agent_versions'] ?? [];
@endphp
<div class="wrap">
    <div class="hero">
        <h1>ObiOra Doctor &amp; Suite — export diagnostic</h1>
        <p><strong>Serveur :</strong> {{ $server->name }} ({{ $server->hostname ?? '—' }}) · ID {{ $server->id }}</p>
        <p><strong>Période :</strong> depuis {{ $since->format('d/m/Y H:i') }} · export {{ \Illuminate\Support\Carbon::parse($payload['exported_at'] ?? now())->format('d/m/Y H:i:s') }}</p>
        <p><strong>Panel :</strong> v{{ $payload['panel_version'] ?? '?' }}</p>
    </div>

    @if(is_array($plain))
    <div class="card">
        <h2>Synthèse</h2>
        <p>{{ $plain['subtitle'] ?? 'Résumé consolidé Doctor, Crash Analyzer et CrashHunter.' }}</p>
        @if(!empty($plain['items']))
        <ul>
            @foreach($plain['items'] as $item)
            <li><strong>{{ $item['title'] ?? '' }}</strong> — {{ $item['explanation'] ?? '' }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endif

    <div class="card">
        <h2>Versions agents</h2>
        <table>
            <thead><tr><th>Agent</th><th>Panel</th><th>Distant</th><th>État</th></tr></thead>
            <tbody>
                @foreach($agents as $row)
                <tr>
                    <td>{{ $row['label'] ?? '' }}</td>
                    <td><code>{{ $row['bundled'] ?? '—' }}</code></td>
                    <td><code>{{ $row['remote'] ?? '—' }}</code></td>
                    <td>
                        @if($row['outdated'] ?? false)
                            <span class="badge badge-warn">MAJ requise</span>
                        @else
                            <span class="badge badge-ok">OK</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(is_array($doctor))
    <div class="card">
        <h2>ObiOra Doctor</h2>
        <dl>
            <dt>Score</dt><dd>{{ $doctor['score'] ?? '—' }}%</dd>
            <dt>Statut</dt><dd>{{ $doctor['status'] ?? '—' }}</dd>
            <dt>Version</dt><dd>{{ $doctor['doctor_version'] ?? '—' }}</dd>
            <dt>Généré</dt><dd>{{ $doctor['generated_at'] ?? '—' }}</dd>
        </dl>
        @if(!empty($doctor['critical_findings']))
        <h3>Findings critiques</h3>
        <ul>
            @foreach($doctor['critical_findings'] as $finding)
            <li>{{ is_array($finding) ? (($finding['module'] ?? '').' — '.($finding['title'] ?? '')) : (string) $finding }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endif

    <div class="card">
        <h2>Crash Analyzer</h2>
        @php($caSummary = $ca['overview']['summary'] ?? [])
        <dl>
            <dt>Métriques (période UI)</dt><dd>{{ $caSummary['metrics_count'] ?? count($ca['metrics'] ?? []) }}@if(!empty($ca['metrics_truncated'])) <span class="muted">(export limité à {{ count($ca['metrics'] ?? []) }} / {{ $ca['metrics_total'] ?? '?' }})</span>@endif</dd>
            <dt>Événements exportés</dt><dd>{{ count($ca['events'] ?? []) }}</dd>
            <dt>Rapports exportés</dt><dd>{{ count($ca['reports'] ?? []) }}</dd>
            <dt>CPU max</dt><dd>{{ $caSummary['cpu_max'] ?? '—' }}%</dd>
            <dt>RAM max</dt><dd>{{ $caSummary['memory_max'] ?? '—' }}%</dd>
        </dl>
        @if(!empty($ca['overview']['hardware']))
        <h3>Inventaire matériel (dernier push)</h3>
        <pre>{{ json_encode($ca['overview']['hardware'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
        @if(!empty($ca['events']))
        <h3>Derniers événements</h3>
        <table>
            <thead><tr><th>Date</th><th>Type</th><th>Sévérité</th><th>Titre</th></tr></thead>
            <tbody>
                @foreach(array_slice($ca['events'], 0, 30) as $event)
                <tr>
                    <td>{{ $event['detected_at'] ?? '' }}</td>
                    <td><code>{{ $event['event_type'] ?? '' }}</code></td>
                    <td>{{ $event['severity'] ?? '' }}</td>
                    <td>{{ $event['title'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="card">
        <h2>CrashHunter</h2>
        @php($chSummary = $ch['overview']['summary'] ?? [])
        <dl>
            <dt>Version distante</dt><dd>{{ $chSummary['version'] ?? '—' }}</dd>
            <dt>Witness</dt><dd>{{ $chSummary['witness_status'] ?? '—' }}</dd>
            <dt>Métriques exportées</dt><dd>{{ count($ch['metrics'] ?? []) }}@if(!empty($ch['metrics_truncated'])) <span class="muted">(limité / {{ $ch['metrics_total'] ?? '?' }} total)</span>@endif</dd>
            <dt>Événements exportés</dt><dd>{{ count($ch['events'] ?? []) }}</dd>
            <dt>Incidents exportés</dt><dd>{{ count($ch['incidents'] ?? []) }}</dd>
            <dt>CPU max (fenêtre UI)</dt><dd>{{ $chSummary['cpu_max'] ?? '—' }}%</dd>
        </dl>
        @if(!empty($ch['events']))
        <h3>Derniers événements</h3>
        <table>
            <thead><tr><th>Date</th><th>Type</th><th>Sévérité</th><th>Titre</th></tr></thead>
            <tbody>
                @foreach(array_slice($ch['events'], 0, 30) as $event)
                <tr>
                    <td>{{ $event['detected_at'] ?? '' }}</td>
                    <td><code>{{ $event['event_type'] ?? '' }}</code></td>
                    <td>{{ $event['severity'] ?? '' }}</td>
                    <td>{{ $event['title'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <p class="muted">Export CSV et JSON disponibles depuis Doctor &amp; Suite pour les métriques brutes complètes.</p>
</div>
</body>
</html>
