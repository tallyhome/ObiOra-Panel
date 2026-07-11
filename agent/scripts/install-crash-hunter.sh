#!/usr/bin/env bash
# ObiOra CrashHunter Enterprise — installation agent + push panel
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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" 2>/dev/null && pwd || echo "/tmp")"
INSTALL_DIR="/opt/crashhunter"
CONFIG_DIR="/etc/obiora"

install_deps() {
    if command -v dnf &>/dev/null; then
        dnf install -y -q python3.12 python3.12-pip gdb perf smartmontools sysstat ipmitool lm_sensors zstd fio stress-ng 2>/dev/null || true
    elif command -v apt-get &>/dev/null; then
        apt-get update -qq
        apt-get install -y -qq python3 python3-venv python3-pip gdb linux-tools-common smartmontools sysstat zstd fio stress-ng 2>/dev/null || true
    fi
}

install_source() {
    mkdir -p "${INSTALL_DIR}"
    if [[ -d "${SCRIPT_DIR}/../ObiOra-Suite/crashhunter" ]]; then
        rsync -a --delete "${SCRIPT_DIR}/../ObiOra-Suite/crashhunter/" "${INSTALL_DIR}/src/" \
            --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' --exclude '.venv' --exclude '*.egg-info'
    elif curl -fsSL "${PANEL_URL%/}/install/crash-hunter.tar.gz" -o /tmp/obiora-crashhunter.tar.gz 2>/dev/null; then
        mkdir -p "${INSTALL_DIR}/src"
        tar -xzf /tmp/obiora-crashhunter.tar.gz -C "${INSTALL_DIR}/src"
        rm -f /tmp/obiora-crashhunter.tar.gz
    else
        echo "ERREUR: source CrashHunter introuvable" >&2
        exit 1
    fi

    python3.12 -m venv "${INSTALL_DIR}/venv" 2>/dev/null || python3 -m venv "${INSTALL_DIR}/venv"
    "${INSTALL_DIR}/venv/bin/pip" install -q --upgrade pip
    "${INSTALL_DIR}/venv/bin/pip" install -q -e "${INSTALL_DIR}/src"
}

write_config() {
    mkdir -p "${CONFIG_DIR}" /dev/shm/crashhunter-ring "${INSTALL_DIR}/data" "${INSTALL_DIR}/reports" "${INSTALL_DIR}/logs"
    if [[ -f "${INSTALL_DIR}/config.yaml" && "${OBIORA_PRESERVE_CONFIG:-no}" == "yes" ]]; then
        echo "Config CrashHunter préservée (${INSTALL_DIR}/config.yaml)"
        return 0
    fi
    cat > "${INSTALL_DIR}/config.yaml" <<YAML
daemon:
  base_dir: ${INSTALL_DIR}
  interval_seconds: 5.0

ring:
  use_tmpfs: true
  tmpfs_path: /dev/shm/crashhunter-ring
  sync_interval_seconds: 30

witness:
  enabled: true
  receiver_url: "${PANEL_URL}"
  host_id: "server-${SERVER_ID}"

panel:
  enabled: true
  url: "${PANEL_URL}"
  server_id: ${SERVER_ID}
  agent_token: "${AGENT_TOKEN}"
  push_interval_seconds: 30
YAML
    chmod 600 "${INSTALL_DIR}/config.yaml"
}

install_deps
install_source
write_config

if [[ -f "${INSTALL_DIR}/src/systemd/crashhunter.service" ]]; then
    sed "s|/opt/crashhunter|${INSTALL_DIR}|g" "${INSTALL_DIR}/src/systemd/crashhunter.service" > /etc/systemd/system/crashhunter.service
else
    cat > /etc/systemd/system/crashhunter.service <<UNIT
[Unit]
Description=ObiOra CrashHunter Enterprise
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
Environment=CRASHHUNTER_CONFIG=${INSTALL_DIR}/config.yaml
ExecStart=${INSTALL_DIR}/venv/bin/crashhunter run
WorkingDirectory=${INSTALL_DIR}
Restart=always
RestartSec=10
Nice=5

[Install]
WantedBy=multi-user.target
UNIT
fi

systemctl daemon-reload
systemctl enable --now crashhunter.service

echo "OK: CrashHunter installé — service crashhunter actif, push vers ${PANEL_URL}"
