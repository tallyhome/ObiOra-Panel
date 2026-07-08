# Module Reboot et surveillance 24h

## Module `reboot`

Analyse instantanee :
- Uptime et date du dernier boot
- Fichier `/var/run/reboot-required` (mise a jour en attente)
- Journal du boot precedent (`journalctl -b -1`)
- Signaux OOM, kernel panic, watchdog
- Score de risque reboot imminent

```bash
./obiora.sh scan --module reboot
```

## Surveillance 24 heures

### Analyse immediate (dernieres 24h de logs)

```bash
./obiora.sh reboot-monitor --analyze
```

Parcourt `journalctl --since "24 hours ago"` et identifie :
- Reboots effectues
- Causes probables (OOM, panic, watchdog, mise a jour, shutdown planifie)
- Risque de reboot imminent (%)

### Surveillance continue (24h en arriere-plan)

```bash
./obiora.sh reboot-monitor --hours 24 --interval 5
```

- Snapshot toutes les 5 minutes
- Historique dans `cache/reboot-watch/`
- Rapport final detaille dans `reports/` + `cache/reboot-watch/reboot-monitor-final.json`

## Rapport detaille

Le rapport inclut :
- Nombre de reboots sur 24h
- Causes probables avec severite
- Extraits du journal
- Score de risque max observe
- Recommandation (maintenance, surveillance, RAS)
