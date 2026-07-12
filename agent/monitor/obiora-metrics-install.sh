#!/usr/bin/env bash
# Installe l'agent métriques ObiOra Monitor (systemd timer 1 min)
set -euo pipefail

PANEL_URL=""
SERVER_ID=""
AGENT_TOKEN=""
INSTALL_ROOT="${OBIORA_MONITOR_ROOT:-/opt/obiora-monitor}"
SCRIPT_SOURCE=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --panel-url=*) PANEL_URL="${1#*=}"; shift ;;
        --server-id=*) SERVER_ID="${1#*=}"; shift ;;
        --agent-token=*) AGENT_TOKEN="${1#*=}"; shift ;;
        --install-root=*) INSTALL_ROOT="${1#*=}"; shift ;;
        --script-source=*) SCRIPT_SOURCE="${1#*=}"; shift ;;
        *) echo "Option inconnue: $1" >&2; exit 1 ;;
    esac
done

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: exécuter en root (sudo)" >&2
    exit 1
fi

if [[ -z "${AGENT_TOKEN}" || -z "${PANEL_URL}" || -z "${SERVER_ID}" ]]; then
    echo "ERREUR: --panel-url, --server-id et --agent-token requis" >&2
    exit 1
fi

mkdir -p "${INSTALL_ROOT}/bin" /etc/obiora /var/log/obiora /var/lib/obiora/metrics-queue
chmod 755 /var/lib/obiora/metrics-queue

PUSH_SCRIPT="${INSTALL_ROOT}/bin/obiora-metrics-push.sh"

if [[ -n "${SCRIPT_SOURCE}" && -f "${SCRIPT_SOURCE}" ]]; then
    install -m 755 "${SCRIPT_SOURCE}" "${PUSH_SCRIPT}"
else
    curl -fsSL "${PANEL_URL%/}/install/obiora-metrics-push.sh" -o "${PUSH_SCRIPT}"
    chmod +x "${PUSH_SCRIPT}"
fi

UNINSTALL_SRC="${SCRIPT_SOURCE%/obiora-metrics-push.sh}/obiora-metrics-uninstall.sh"
if [[ -f "${UNINSTALL_SRC}" ]]; then
    install -m 755 "${UNINSTALL_SRC}" "${INSTALL_ROOT}/bin/obiora-metrics-uninstall.sh"
else
    curl -fsSL "${PANEL_URL%/}/install/obiora-metrics-uninstall.sh" -o "${INSTALL_ROOT}/bin/obiora-metrics-uninstall.sh" 2>/dev/null || true
    chmod +x "${INSTALL_ROOT}/bin/obiora-metrics-uninstall.sh" 2>/dev/null || true
fi

cat > /etc/obiora/monitor-agent.env <<ENV
OBIORA_PANEL_URL="${PANEL_URL%/}"
OBIORA_SERVER_ID="${SERVER_ID}"
OBIORA_AGENT_TOKEN="${AGENT_TOKEN}"
OBIORA_MONITOR_ENV="/etc/obiora/monitor-agent.env"
OBIORA_METRICS_QUEUE="/var/lib/obiora/metrics-queue"
OBIORA_METRICS_LOG="/var/log/obiora/metrics-agent.log"
ENV
chmod 600 /etc/obiora/monitor-agent.env

cat > /etc/systemd/system/obiora-metrics.service <<SERVICE
[Unit]
Description=ObiOra Monitor metrics push
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
EnvironmentFile=/etc/obiora/monitor-agent.env
ExecStart=${PUSH_SCRIPT}
SERVICE

cat > /etc/systemd/system/obiora-metrics.timer <<TIMER
[Unit]
Description=ObiOra Monitor metrics timer (every minute)

[Timer]
OnCalendar=minutely
AccuracySec=1s
Persistent=true
Unit=obiora-metrics.service

[Install]
WantedBy=timers.target
TIMER

systemctl daemon-reload
systemctl enable --now obiora-metrics.timer

# Premier push immédiat
"${PUSH_SCRIPT}" || true

echo "OK: agent métriques ObiOra installé (server #${SERVER_ID})"
echo "Logs: /var/log/obiora/metrics-agent.log"
