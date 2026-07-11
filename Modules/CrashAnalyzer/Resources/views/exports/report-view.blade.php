<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapport Crash Analyzer — {{ $server->name }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
        .hero { background: linear-gradient(135deg, #7f1d1d, #991b1b); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
        .hero h1 { margin: 0 0 .5rem; font-size: 1.35rem; }
        .hero p { margin: .25rem 0; opacity: .95; }
        .card { background: #1e293b; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .card h2 { margin: 0 0 .75rem; font-size: 1rem; color: #93c5fd; }
        ul { margin: .5rem 0 0; padding-left: 1.2rem; }
        table { border-collapse: collapse; width: 100%; font-size: .85rem; }
        th, td { border: 1px solid #334155; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #334155; }
        .badge { display: inline-block; padding: .15rem .45rem; border-radius: 4px; font-size: .75rem; background: #dc2626; }
        pre { background: #0b1220; padding: .75rem; border-radius: 8px; overflow: auto; font-size: .78rem; }
        @media print { body { background: #fff; color: #111; } .card, .hero { background: #f8fafc; color: #111; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>{{ $triggerLabel }}</h1>
        <p><strong>Serveur :</strong> {{ $server->name }} ({{ $server->hostname }})</p>
        <p><strong>Rapport :</strong> {{ $report->external_id }} · <strong>Généré :</strong> {{ $report->generated_at?->format('d/m/Y H:i:s') }}</p>
    </div>

    <div class="card">
        <h2>Que s'est-il passé ?</h2>
        <p>{{ $triggerLabel }}</p>
        @if(!empty($hints))
        <p><strong>Pistes :</strong></p>
        <ul>
            @foreach($hints as $hint)
            <li>{{ $hint }}</li>
            @endforeach
        </ul>
        @endif
    </div>

    @if(!empty($summary))
    <div class="card">
        <h2>Résumé métriques (fenêtre pré-crash)</h2>
        <pre>{{ json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    @endif

    <div class="card">
        <h2>Événements capturés</h2>
        <table>
            <thead><tr><th>Type</th><th>Sévérité</th><th>Titre</th><th>Détails</th></tr></thead>
            <tbody>
                @forelse($events as $event)
                <tr>
                    <td><code>{{ $event['event_type'] ?? '' }}</code></td>
                    <td><span class="badge">{{ $event['severity'] ?? '' }}</span></td>
                    <td>{{ $event['title'] ?? '' }}</td>
                    <td>{{ Str::limit($event['details'] ?? '', 300) }}</td>
                </tr>
                @empty
                <tr><td colspan="4">Aucun événement dans ce rapport.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
