<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="5">
    <title>ObiOra Panel — indisponible</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
        .box { max-width: 36rem; padding: 2rem; background: #1e293b; border-radius: 12px; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; color: #38bdf8; }
        p { color: #94a3b8; line-height: 1.6; margin: 0.5rem 0; }
        .retry { margin-top: 1rem; color: #64748b; font-size: 0.875rem; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #0ea5e9; color: #0f172a; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #38bdf8; }
        code { font-size: 0.8rem; word-break: break-all; }
        .diag { text-align: left; margin: 1rem 0; padding: 0.75rem 1rem; background: #0f172a; border-radius: 8px; font-size: 0.85rem; }
        .ok { color: #4ade80; }
        .ko { color: #f87171; }
        ul { margin: 0.5rem 0 0; padding-left: 1.2rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="box">
        <h1>ObiOra Panel — démarrage en cours</h1>
        <p>Le panel est temporairement indisponible (MariaDB ou surcharge momentanée).</p>

        @if(!empty($diagnostics))
        <div class="diag">
            <div>MariaDB : <strong class="{{ ($diagnostics['database'] ?? false) ? 'ok' : 'ko' }}">{{ ($diagnostics['database'] ?? false) ? 'OK' : 'KO' }}</strong></div>
            @if($diagnostics['redis_required'] ?? false)
            <div>Redis : <strong class="{{ ($diagnostics['redis'] ?? false) ? 'ok' : 'ko' }}">{{ ($diagnostics['redis'] ?? false) ? 'OK' : 'KO (cache)' }}</strong></div>
            @endif
            @if(!empty($diagnostics['database_error']))
            <div class="small text-muted mt-1" style="word-break:break-word;">{{ $diagnostics['database_error'] }}</div>
            @endif
            @if(!empty($diagnostics['hints']))
            <ul>
                @foreach($diagnostics['hints'] as $hint)
                <li>{{ $hint }}</li>
                @endforeach
            </ul>
            @endif
        </div>
        @endif

        <p>La page se rafraîchit automatiquement toutes les <strong>5 secondes</strong>.</p>
        <button type="button" class="btn" onclick="window.location.reload()">Réessayer maintenant</button>
        <p class="retry">Diagnostic JSON : <code>/panel-health</code></p>
        <p class="retry">Récupération SSH (root) :</p>
        <p class="retry"><code>sudo bash /opt/obiora-panel/agent/scripts/panel-recover-ssh.sh</code></p>
    </div>
</body>
</html>
