#!/usr/bin/env bash
# ObiOra Crash Analyzer — installation agent
# Usage: sudo OBIORA_PANEL_URL=... OBIORA_SERVER_ID=... OBIORA_AGENT_TOKEN=... bash install-crash-analyzer.sh
set -euo pipefail

PANEL_URL="${OBIORA_PANEL_URL:?OBIORA_PANEL_URL requis}"
SERVER_ID="${OBIORA_SERVER_ID:?OBIORA_SERVER_ID requis}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?OBIORA_AGENT_TOKEN requis}"

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n env \
        OBIORA_PANEL_URL="${PANEL_URL}" \
        OBIORA_SERVER_ID="${SERVER_ID}" \
        OBIORA_AGENT_TOKEN="${AGENT_TOKEN}" \
        bash "$0" "$@"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_DIR="/opt/obiora-crash-analyzer"
CONFIG_DIR="/etc/obiora"
DATA_DIR="/var/lib/obiora/crash-analyzer"

detect_os() {
    if [[ -f /etc/os-release ]]; then
        # shellcheck source=/dev/null
        source /etc/os-release
        echo "${ID:-unknown}"
    else
        echo "unknown"
    fi
}

install_deps() {
    local os
    os="$(detect_os)"
    case "${os}" in
        debian|ubuntu)
            apt-get update -qq
            apt-get install -y -qq python3 python3-venv smartmontools iproute2 systemd
            ;;
        almalinux|rocky|centos|rhel)
            dnf install -y -q python3 python3-pip smartmontools iproute systemd
            ;;
        *)
            echo "OS ${os} — installation manuelle des dépendances requise" >&2
            ;;
    esac
}

install_deps
mkdir -p "${INSTALL_DIR}" "${CONFIG_DIR}" "${DATA_DIR}/reports"

if [[ -d "${SCRIPT_DIR}/../crash-analyzer" ]]; then
    rsync -a --delete "${SCRIPT_DIR}/../crash-analyzer/" "${INSTALL_DIR}/" \
        --exclude '__pycache__' --exclude '*.pyc' --exclude 'tests'
else
    echo "ERREUR: répertoire crash-analyzer introuvable" >&2
    exit 1
fi

python3 -m venv "${INSTALL_DIR}/venv"
"${INSTALL_DIR}/venv/bin/pip" install -q -r "${INSTALL_DIR}/requirements.txt" 2>/dev/null || true

cat > "${CONFIG_DIR}/crash-analyzer.json" <<JSON
{
  "interval_seconds": 5,
  "history_minutes": 60,
  "storage_backend": "sqlite",
  "sqlite_path": "${DATA_DIR}/metrics.db",
  "panel_url": "${PANEL_URL}",
  "server_id": "${SERVER_ID}",
  "agent_token": "${AGENT_TOKEN}",
  "push_interval_seconds": 30,
  "reports_dir": "${DATA_DIR}/reports",
  "state_file": "${DATA_DIR}/state.json"
}
JSON
chmod 600 "${CONFIG_DIR}/crash-analyzer.json"

cat > /etc/systemd/system/obiora-crash-analyzer.service <<UNIT
[Unit]
Description=ObiOra Crash Analyzer — surveillance pré/post crash
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=${INSTALL_DIR}/venv/bin/python -m crash_analyzer -c ${CONFIG_DIR}/crash-analyzer.json
WorkingDirectory=${INSTALL_DIR}
Restart=always
RestartSec=10
Nice=10
IOSchedulingClass=idle
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now obiora-crash-analyzer.service

echo "OK: Crash Analyzer installé — service obiora-crash-analyzer actif (intervalle 5s)"
