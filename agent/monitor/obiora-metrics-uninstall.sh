#!/usr/bin/env bash
# Désinstalle l'agent métriques ObiOra Monitor
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: exécuter en root (sudo)" >&2
    exit 1
fi

systemctl stop obiora-metrics.timer 2>/dev/null || true
systemctl disable obiora-metrics.timer 2>/dev/null || true
systemctl stop obiora-metrics.service 2>/dev/null || true
rm -f /etc/systemd/system/obiora-metrics.service /etc/systemd/system/obiora-metrics.timer
systemctl daemon-reload 2>/dev/null || true

rm -f /etc/cron.d/obiora-metrics 2>/dev/null || true
rm -f /etc/obiora/monitor-agent.env 2>/dev/null || true

echo "OK: agent métriques ObiOra arrêté (logs et queue conservés sous /var/log/obiora et /var/lib/obiora)"
