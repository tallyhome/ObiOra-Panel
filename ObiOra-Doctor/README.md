# Obiora Doctor v0.4.0

## Lancement simple (un seul fichier)

```bash
chmod +x obiora.sh
./obiora.sh
```

Le menu interactif propose toutes les options sans memoriser les commandes.

## Interface web securisee (production)

```bash
./obiora.sh web
```

Securite :
- Lie a **127.0.0.1 uniquement** (jamais expose publiquement)
- Token d'authentification (`config/web.token`, chmod 600)
- Rate limit : 1 scan / 60 secondes
- Headers securite (CSP, X-Frame-Options, etc.)
- Lecture seule par defaut

Acces distant via tunnel SSH :
```bash
ssh -L 8766:127.0.0.1:8766 root@votre-serveur
# Puis ouvrir http://127.0.0.1:8766
```

## Module Reboot + surveillance 24h

```bash
./obiora.sh scan --module reboot          # Analyse instantanee
./obiora.sh reboot-monitor --analyze      # Rapport 24h immediat
./obiora.sh reboot-monitor --hours 24     # Surveillance continue 24h
```

Detecte : OOM, kernel panic, watchdog, reboot requis, historique reboot, risque imminent.

Rapport detaille : `docs/REPORT.md`
