#!/usr/bin/env bash
# ObiOra Crash Analyzer — installation agent
# Usage local  : sudo OBIORA_PANEL_URL=... OBIORA_SERVER_ID=... OBIORA_AGENT_TOKEN=... bash install-crash-analyzer.sh
# Usage distant: curl -fsSL https://panel/install/crash-analyzer.sh | sudo OBIORA_... bash
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
            apt-get install -y -qq \
                python3 python3-venv smartmontools iproute2 systemd curl tar rsync \
                strace dmidecode pciutils util-linux
            ;;
        almalinux|rocky|centos|rhel)
            dnf install -y -q \
                python3 python3-pip smartmontools iproute systemd curl tar rsync \
                strace time dmidecode pciutils
            ;;
        *)
            echo "OS ${os} — installez python3, strace, time, dmidecode, pciutils manuellement si besoin" >&2
            ;;
    esac
}

setup_persistent_journal() {
    echo "Configuration du journal systemd persistant…"
    mkdir -p /var/log/journal
    systemd-tmpfiles --create --prefix /var/log/journal 2>/dev/null || true

    local conf="/etc/systemd/journald.conf"
    if [[ -f "${conf}" ]]; then
        if grep -qE '^#?Storage=' "${conf}"; then
            sed -i 's/^#\?Storage=.*/Storage=persistent/' "${conf}"
        else
            echo "Storage=persistent" >> "${conf}"
        fi
        systemctl restart systemd-journald 2>/dev/null || true
    fi

    if journalctl --list-boots --no-pager 2>/dev/null | head -1 | grep -q .; then
        echo "OK: journal persistant actif ($(journalctl --list-boots --no-pager 2>/dev/null | wc -l | tr -d ' ') boots listés)"
    else
        echo "AVERTISSEMENT: journalctl --list-boots indisponible — vérifiez systemd-journald" >&2
    fi
}

verify_tools() {
    echo "Outils diagnostic:"
    for bin in strace time dmidecode lscpu lspci journalctl; do
        if command -v "${bin}" >/dev/null 2>&1; then
            echo "  ✔ ${bin} → $(command -v "${bin}")"
        else
            echo "  ✗ ${bin} manquant" >&2
        fi
    done
}

install_agent_source() {
    mkdir -p "${INSTALL_DIR}"

    if [[ -d "${SCRIPT_DIR}/../crash-analyzer" ]]; then
        rsync -a --delete "${SCRIPT_DIR}/../crash-analyzer/" "${INSTALL_DIR}/" \
            --exclude '__pycache__' --exclude '*.pyc' --exclude 'tests'
        return 0
    fi

    local tmp_tar
    tmp_tar="$(mktemp /tmp/obiora-crash-analyzer.XXXXXX.tar.gz)"
    echo "Téléchargement de l'agent Crash Analyzer depuis ${PANEL_URL}…"
    if ! curl -fsSL "${PANEL_URL%/}/install/crash-analyzer.tar.gz" -o "${tmp_tar}"; then
        rm -f "${tmp_tar}"
        echo "ERREUR: impossible de télécharger /install/crash-analyzer.tar.gz depuis le panel" >&2
        exit 1
    fi
    tar -xzf "${tmp_tar}" -C "${INSTALL_DIR}"
    rm -f "${tmp_tar}"
}

install_deps
setup_persistent_journal
verify_tools
mkdir -p "${CONFIG_DIR}" "${DATA_DIR}/reports"
install_agent_source

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
