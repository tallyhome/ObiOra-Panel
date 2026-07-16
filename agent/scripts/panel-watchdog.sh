#!/usr/bin/env bash
# Watchdog overnight ObiOra Panel — auto-réparation si login/health KO.
# Installé via systemd timer (toutes les 2 min). Root uniquement.
# Usage : sudo bash /opt/obiora-panel/agent/scripts/panel-watchdog.sh
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"
OBIORA_GROUP="${OBIORA_GROUP:-obiora}"
LOCK_FILE="${LOCK_FILE:-/run/obiora-panel-watchdog.lock}"
LOG_FILE="${LOG_FILE:-/var/log/obiora-panel-watchdog.log}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1/panel-health}"
LOGIN_URL="${LOGIN_URL:-http://127.0.0.1/login}"

log() {
    local line="[$(date '+%Y-%m-%d %H:%M:%S')] $*"
    echo "${line}" | tee -a "${LOG_FILE}" >/dev/null
    echo "[panel-watchdog] $*"
}

warn() { log "WARN: $*"; }

http_code() {
    local url="$1"
    curl -sS -o /dev/null -w '%{http_code}' --max-time 8 "${url}" 2>/dev/null || echo '000'
}

panel_ok() {
    local health login
    health="$(http_code "${HEALTH_URL}")"
    login="$(http_code "${LOGIN_URL}")"
    # 200 = OK ; 302/301 sur login = session/redirect OK aussi
    [[ "${health}" == "200" ]] && [[ "${login}" =~ ^(200|301|302)$ ]]
}

wait_for_mysql() {
    local tries="${1:-20}" i
    for ((i = 1; i <= tries; i++)); do
        if mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null \
            || mysqladmin ping --silent 2>/dev/null; then
            return 0
        fi
        sleep 1
    done
    return 1
}

disable_broadcast_if_reverb_down() {
    cd "${OBIORA_INSTALL_DIR}"
    if ! systemctl list-unit-files 2>/dev/null | grep -q '^obiora-reverb\.service'; then
        return 0
    fi
    if systemctl is-active --quiet obiora-reverb; then
        return 0
    fi
    warn "obiora-reverb down — BROADCAST_CONNECTION=null"
    if grep -q '^BROADCAST_CONNECTION=' .env 2>/dev/null; then
        sed -i 's|^BROADCAST_CONNECTION=.*|BROADCAST_CONNECTION=null|' .env
    else
        echo 'BROADCAST_CONNECTION=null' >> .env
    fi
    if grep -q '^OBIORA_REALTIME_ENABLED=' .env 2>/dev/null; then
        sed -i 's|^OBIORA_REALTIME_ENABLED=.*|OBIORA_REALTIME_ENABLED=false|' .env
    else
        echo 'OBIORA_REALTIME_ENABLED=false' >> .env
    fi
}

fix_storage_permissions() {
    cd "${OBIORA_INSTALL_DIR}"
    mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
    chown -R "${OBIORA_USER}:${OBIORA_GROUP}" storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache
    find storage/logs -type f -name '*.log' -exec chown "${OBIORA_USER}:${OBIORA_GROUP}" {} \; 2>/dev/null || true
}

reclaim_disk_if_needed() {
    local avail_kb
    avail_kb="$(df -Pk /opt 2>/dev/null | awk 'NR==2 {print $4}' || df -Pk / | awk 'NR==2 {print $4}')"
    if [[ -n "${avail_kb}" ]] && (( avail_kb < 800000 )); then
        warn "Disque faible (${avail_kb} Ko) — purge CrashHunter"
        if [[ -x "${OBIORA_INSTALL_DIR}/agent/scripts/crashhunter-disk-purge.sh" ]]; then
            bash "${OBIORA_INSTALL_DIR}/agent/scripts/crashhunter-disk-purge.sh" keep 2 || true
        fi
        journalctl --vacuum-size=80M 2>/dev/null || true
    fi
}

heal_light() {
    log "Heal léger…"
    reclaim_disk_if_needed

    systemctl start mariadb 2>/dev/null || systemctl start mysqld 2>/dev/null || true
    systemctl start redis 2>/dev/null || systemctl start redis-server 2>/dev/null || true
    systemctl start php-fpm 2>/dev/null || true
    systemctl start nginx 2>/dev/null || true
    systemctl start obiora-queue 2>/dev/null || true
    systemctl enable --now obiora-reverb 2>/dev/null || systemctl start obiora-reverb 2>/dev/null || true

    wait_for_mysql 30 || warn "MySQL pas prêt après start"
    disable_broadcast_if_reverb_down
    fix_storage_permissions

    cd "${OBIORA_INSTALL_DIR}"
    sudo -u "${OBIORA_USER}" php artisan optimize:clear 2>/dev/null || true

    systemctl reload php-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
    systemctl reload nginx 2>/dev/null || systemctl restart nginx 2>/dev/null || true
}

heal_heavy() {
    log "Heal lourd (recover sans git)…"
    if [[ -x "${OBIORA_INSTALL_DIR}/agent/scripts/panel-recover-ssh.sh" ]]; then
        SKIP_GIT=1 bash "${OBIORA_INSTALL_DIR}/agent/scripts/panel-recover-ssh.sh" || true
    else
        heal_light
    fi
}

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: root requis — sudo bash $0" >&2
    exit 1
fi

mkdir -p "$(dirname "${LOG_FILE}")"
touch "${LOG_FILE}"
chmod 640 "${LOG_FILE}" 2>/dev/null || true

exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
    log "Déjà en cours — skip"
    exit 0
fi

if [[ ! -d "${OBIORA_INSTALL_DIR}" ]]; then
    warn "Install introuvable: ${OBIORA_INSTALL_DIR}"
    exit 1
fi

cd "${OBIORA_INSTALL_DIR}"

if panel_ok; then
    exit 0
fi

health="$(http_code "${HEALTH_URL}")"
login="$(http_code "${LOGIN_URL}")"
log "Panel KO (health=${health} login=${login}) — auto-heal"

heal_light
sleep 2

if panel_ok; then
    log "OK après heal léger (health=$(http_code "${HEALTH_URL}") login=$(http_code "${LOGIN_URL}"))"
    exit 0
fi

heal_heavy
sleep 3

if panel_ok; then
    log "OK après heal lourd (health=$(http_code "${HEALTH_URL}") login=$(http_code "${LOGIN_URL}"))"
    exit 0
fi

log "ÉCHEC: panel toujours KO health=$(http_code "${HEALTH_URL}") login=$(http_code "${LOGIN_URL}"))"
exit 1
