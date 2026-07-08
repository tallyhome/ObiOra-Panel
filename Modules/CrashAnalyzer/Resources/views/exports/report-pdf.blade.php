<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport Crash Analyzer — {{ $server->name }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #1a1a2e; }
        h1 { color: #c0392b; }
        table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 0.85rem; }
        th { background: #2c3e50; color: #fff; }
        @media print { body { margin: 1cm; } }
    </style>
</head>
<body>
    <h1>Rapport Crash Analyzer</h1>
    <p><strong>Serveur :</strong> {{ $server->name }} ({{ $server->hostname }})</p>
    <p><strong>Rapport :</strong> {{ $report->external_id }}</p>
    <p><strong>Généré :</strong> {{ $report->generated_at?->format('Y-m-d H:i:s') }}</p>
    <p><strong>Déclencheur :</strong> {{ $report->trigger_type }}</p>

    <h2>Résumé métriques</h2>
    <pre>{{ json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

    <h2>Événements</h2>
    <table>
        <thead><tr><th>Type</th><th>Sévérité</th><th>Titre</th><th>Détails</th></tr></thead>
        <tbody>
            @foreach($events as $event)
            <tr>
                <td>{{ $event['event_type'] ?? '' }}</td>
                <td>{{ $event['severity'] ?? '' }}</td>
                <td>{{ $event['title'] ?? '' }}</td>
                <td>{{ Str::limit($event['details'] ?? '', 200) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
