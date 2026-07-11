#!/usr/bin/env bash
# ObiOra Doctor & Suite — désinstallation complète (services + fichiers diagnostics)
# Usage: curl -fsSL https://panel/install/uninstall-doctor-suite.sh | sudo bash
set -euo pipefail

PURGE_DOCTOR="${OBIORA_PURGE_DOCTOR:-yes}"
PURGE_CRASH="${OBIORA_PURGE_CRASH:-yes}"
PURGE_HUNTER="${OBIORA_PURGE_HUNTER:-yes}"
PURGE_SLAVE="${OBIORA_PURGE_SLAVE:-yes}"
REMOVE_PANEL_KEYS="${OBIORA_REMOVE_PANEL_KEYS:-yes}"

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n env \
        OBIORA_PURGE_DOCTOR="${PURGE_DOCTOR}" \
        OBIORA_PURGE_CRASH="${PURGE_CRASH}" \
        OBIORA_PURGE_HUNTER="${PURGE_HUNTER}" \
        OBIORA_PURGE_SLAVE="${PURGE_SLAVE}" \
        OBIORA_REMOVE_PANEL_KEYS="${REMOVE_PANEL_KEYS}" \
        bash "$0" "$@"
fi

stop_disable() {
    local unit="$1"
    systemctl stop "${unit}" 2>/dev/null || true
    systemctl disable "${unit}" 2>/dev/null || true
}

remove_unit() {
    local unit="$1"
    stop_disable "${unit}"
    rm -f "/etc/systemd/system/${unit}"
}

echo "=== ObiOra Doctor & Suite — désinstallation ==="

if [[ "${PURGE_HUNTER}" == "yes" ]]; then
    echo "[crashhunter] Arrêt et suppression…"
    remove_unit "crashhunter.service"
    rm -rf /opt/crashhunter
    rm -rf /dev/shm/crashhunter-ring
fi

if [[ "${PURGE_CRASH}" == "yes" ]]; then
    echo "[crash-analyzer] Arrêt et suppression…"
    remove_unit "obiora-crash-analyzer.service"
    rm -rf /opt/obiora-crash-analyzer
    rm -rf /var/lib/obiora/crash-analyzer
    rm -f /etc/obiora/crash-analyzer.json
fi

if [[ "${PURGE_DOCTOR}" == "yes" ]]; then
    echo "[doctor] Arrêt et suppression…"
    stop_disable "obiora-doctor-agent.timer"
    remove_unit "obiora-doctor-agent.service"
    remove_unit "obiora-doctor-agent.timer"
    rm -rf /opt/obiora-doctor-agent
fi

if [[ "${PURGE_SLAVE}" == "yes" ]]; then
    echo "[seedbox slave] Arrêt et suppression…"
    remove_unit "obiora-agent.service"
    rm -rf /opt/obiora-slave
fi

systemctl daemon-reload 2>/dev/null || true

if [[ "${REMOVE_PANEL_KEYS}" == "yes" ]] && [[ -f /root/.ssh/authorized_keys ]]; then
    echo "[ssh] Retrait des clés ObiOra Panel…"
    grep -v 'obiora-panel-server-' /root/.ssh/authorized_keys > /root/.ssh/authorized_keys.obiora_tmp || true
    mv /root/.ssh/authorized_keys.obiora_tmp /root/.ssh/authorized_keys
    chmod 600 /root/.ssh/authorized_keys
fi

# Nettoyage répertoire obiora si vide (conserve mysql-admin.cnf etc.)
if [[ -d /etc/obiora ]] && [[ -z "$(ls -A /etc/obiora 2>/dev/null)" ]]; then
    rmdir /etc/obiora 2>/dev/null || true
fi

if [[ -d /var/lib/obiora ]] && [[ -z "$(ls -A /var/lib/obiora 2>/dev/null)" ]]; then
    rmdir /var/lib/obiora 2>/dev/null || true
fi

echo "OBIORA_SUITE_PURGED:OK"
echo "OK: agents diagnostics supprimés (Doctor=${PURGE_DOCTOR}, Crash=${PURGE_CRASH}, Hunter=${PURGE_HUNTER}, Slave=${PURGE_SLAVE})"
