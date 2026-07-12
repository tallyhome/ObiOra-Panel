#!/usr/bin/env bash
# Désinstalle agent ObiOra Monitor + slave optionnel
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: exécuter en root (sudo)" >&2
    exit 1
fi

for script in \
    /opt/obiora-monitor/bin/obiora-metrics-uninstall.sh \
    /opt/obiora-panel/agent/monitor/obiora-metrics-uninstall.sh; do
    if [[ -x "${script}" ]]; then
        bash "${script}"
        exit 0
    fi
done

# Fallback legacy
systemctl stop obiora-metrics.timer obiora-agent 2>/dev/null || true
systemctl disable obiora-metrics.timer obiora-agent 2>/dev/null || true
rm -f /etc/systemd/system/obiora-metrics.service /etc/systemd/system/obiora-metrics.timer
rm -f /etc/systemd/system/obiora-agent.service
rm -f /etc/cron.d/obiora-metrics /etc/obiora/monitor-agent.env
systemctl daemon-reload 2>/dev/null || true

echo "OK: agents ObiOra monitor arrêtés"
