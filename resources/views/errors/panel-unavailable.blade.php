<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="5">
    <title>ObiOra Panel — indisponible</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { max-width: 32rem; padding: 2rem; background: #1e293b; border-radius: 12px; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; color: #38bdf8; }
        p { color: #94a3b8; line-height: 1.6; margin: 0.5rem 0; }
        .retry { margin-top: 1.5rem; color: #64748b; font-size: 0.875rem; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #0ea5e9; color: #0f172a; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #38bdf8; }
        code { font-size: 0.8rem; word-break: break-all; }
    </style>
</head>
<body>
    <div class="box">
        <h1>ObiOra Panel — démarrage en cours</h1>
        <p>Le panel est temporairement indisponible (base de données, Redis ou surcharge momentanée).</p>
        <p>La page se rafraîchit automatiquement toutes les <strong>5 secondes</strong>.</p>
        <button type="button" class="btn" onclick="window.location.reload()">Réessayer maintenant</button>
        <p class="retry">Si le problème persiste après 1–2 minutes :</p>
        <p class="retry"><code>sudo systemctl restart php-fpm nginx mariadb redis obiora-queue</code></p>
        <p class="retry"><code>curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1/up</code> — doit répondre <strong>200</strong></p>
    </div>
</body>
</html>
