<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="15">
    <title>ObiOra Panel — indisponible</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { max-width: 32rem; padding: 2rem; background: #1e293b; border-radius: 12px; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; color: #38bdf8; }
        p { color: #94a3b8; line-height: 1.6; margin: 0.5rem 0; }
        .retry { margin-top: 1.5rem; color: #64748b; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>ObiOra Panel — démarrage en cours</h1>
        <p>Le panel est temporairement indisponible (base de données, Redis ou assets en cours de démarrage après un reboot).</p>
        <p>La page se rafraîchira automatiquement dans quelques secondes.</p>
        <p class="retry">Si le problème persiste : <code>systemctl status mariadb redis php-fpm nginx obiora-queue</code></p>
    </div>
</body>
</html>
