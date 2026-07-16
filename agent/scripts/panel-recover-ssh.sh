#!/usr/bin/env bash
# Récupération panel ObiOra via SSH (MariaDB, .env, MAJ code, caches, services).
# Usage : sudo bash /opt/obiora-panel/agent/scripts/panel-recover-ssh.sh
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"
OBIORA_GROUP="${OBIORA_GROUP:-obiora}"

log() { echo "[panel-recover] $*"; }
warn() { echo "[panel-recover] WARN: $*" >&2; }

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: exécuter en root — sudo bash $0" >&2
    exit 1
fi

if [[ ! -d "${OBIORA_INSTALL_DIR}" ]]; then
    echo "ERREUR: ${OBIORA_INSTALL_DIR} introuvable" >&2
    exit 1
fi

cd "${OBIORA_INSTALL_DIR}"

log "1/9 — Espace disque (souvent cause du 500 login overnight)…"
df -h / /opt 2>/dev/null || df -h /
avail_kb="$(df -Pk /opt 2>/dev/null | awk 'NR==2 {print $4}' || df -Pk / | awk 'NR==2 {print $4}')"
if [[ -n "${avail_kb}" ]] && (( avail_kb < 800000 )); then
    warn "Disque faible (${avail_kb} Ko libres) — purge CrashHunter bundles…"
    if [[ -x "${OBIORA_INSTALL_DIR}/agent/scripts/crashhunter-disk-purge.sh" ]]; then
        bash "${OBIORA_INSTALL_DIR}/agent/scripts/crashhunter-disk-purge.sh" keep 2 || true
    else
        rm -rf /opt/crashhunter/bundles/* /opt/crashhunter/reports/* 2>/dev/null || true
    fi
    journalctl --vacuum-size=100M 2>/dev/null || true
    df -h / /opt 2>/dev/null || true
fi

log "2/9 — Démarrage MariaDB, Redis, PHP-FPM, Nginx, queue, Reverb…"
systemctl start mariadb 2>/dev/null || systemctl start mysqld 2>/dev/null || true
systemctl start redis 2>/dev/null || systemctl start redis-server 2>/dev/null || true
systemctl start php-fpm 2>/dev/null || true
systemctl start nginx 2>/dev/null || true
systemctl start obiora-queue 2>/dev/null || true
systemctl start obiora-reverb 2>/dev/null || true

# Si Reverb reste down : désactiver le broadcast pour éviter les 500 login/dashboard
if systemctl list-unit-files 2>/dev/null | grep -q '^obiora-reverb\.service'; then
    if ! systemctl is-active --quiet obiora-reverb; then
        warn "obiora-reverb inactif — BROADCAST_CONNECTION=null (polling UI)"
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
    fi
fi

if ! systemctl is-active mariadb &>/dev/null && ! systemctl is-active mysqld &>/dev/null; then
    warn "MariaDB ne démarre pas — journal :"
    journalctl -u mariadb -n 20 --no-pager 2>/dev/null || journalctl -u mysqld -n 20 --no-pager 2>/dev/null || true
    exit 1
fi

log "3/9 — Tuning MariaDB (petits VPS)…"
if [[ -f "${OBIORA_INSTALL_DIR}/agent/scripts/mariadb-tune-panel.sh" ]]; then
    bash "${OBIORA_INSTALL_DIR}/agent/scripts/mariadb-tune-panel.sh" || warn "tuning MariaDB ignoré"
fi

log "4/9 — Synchronisation mot de passe BDD → .env…"
if [[ -f /root/.obiora_db_credentials ]]; then
    # shellcheck source=/dev/null
    source /root/.obiora_db_credentials
    if [[ -n "${DB_USERNAME:-}" && -n "${DB_PASSWORD:-}" && -n "${DB_DATABASE:-}" ]]; then
        if grep -q '^DB_PASSWORD=' .env 2>/dev/null; then
            sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
        fi
        if grep -q '^DB_USERNAME=' .env 2>/dev/null; then
            sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|" .env
        fi
        if grep -q '^DB_DATABASE=' .env 2>/dev/null; then
            sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|" .env
        fi
        sed -i 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' .env
        sed -i 's|^DB_CONNECTION=.*|DB_CONNECTION=mysql|' .env
    fi
fi

# Cache sur BDD si petit VPS (évite dépendance Redis)
if grep -q '^CACHE_STORE=redis' .env 2>/dev/null; then
    ram_mb="$(awk '/MemTotal:/ {print int($2/1024)}' /proc/meminfo 2>/dev/null || echo 8192)"
    if (( ram_mb <= 4096 )); then
        sed -i 's|^CACHE_STORE=.*|CACHE_STORE=database|' .env
        log "CACHE_STORE=database (RAM ${ram_mb} MiB)"
    fi
fi

log "5/9 — Test connexion MySQL…"
mysql_ok=0
if [[ -f /root/.obiora_db_credentials ]]; then
    # shellcheck source=/dev/null
    source /root/.obiora_db_credentials
    if mysql -N -u "${DB_USERNAME}" -p"${DB_PASSWORD}" -h 127.0.0.1 "${DB_DATABASE}" -e "SELECT 1" &>/dev/null; then
        mysql_ok=1
    elif mysql -N -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -e "SELECT 1" &>/dev/null; then
        mysql_ok=1
        sed -i 's|^DB_HOST=.*|DB_HOST=localhost|' .env
        log "Connexion via socket — DB_HOST=localhost"
    fi
fi

if [[ "${mysql_ok}" -eq 0 ]]; then
    warn "Connexion MySQL échouée — resynchronisation utilisateur obiora…"
    if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/database.sh" ]] && [[ -f /root/.obiora_db_credentials ]]; then
        # shellcheck source=/dev/null
        source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
        # shellcheck source=/dev/null
        source "${OBIORA_INSTALL_DIR}/install/lib/database.sh"
        OBIORA_DB_PASS="$(grep '^DB_PASSWORD=' /root/.obiora_db_credentials | cut -d= -f2-)"
        OBIORA_DB_USER="$(grep '^DB_USERNAME=' /root/.obiora_db_credentials | cut -d= -f2-)"
        OBIORA_DB_NAME="$(grep '^DB_DATABASE=' /root/.obiora_db_credentials | cut -d= -f2-)"
        mysql_exec <<SQL || true
ALTER USER '${OBIORA_DB_USER}'@'localhost' IDENTIFIED BY '${OBIORA_DB_PASS}';
ALTER USER '${OBIORA_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${OBIORA_DB_PASS}';
FLUSH PRIVILEGES;
SQL
        sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${OBIORA_DB_PASS}|" .env
    fi
fi

log "6/9 — Mise à jour code (git pull)…"
if [[ -d .git ]]; then
    git config --global --add safe.directory "${OBIORA_INSTALL_DIR}" 2>/dev/null || true
    sudo -u "${OBIORA_USER}" git fetch origin main 2>/dev/null || git fetch origin main
    sudo -u "${OBIORA_USER}" git checkout -B main origin/main 2>/dev/null || git checkout -B main origin/main
    chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}"
fi

log "7/9 — Caches Laravel + migrations…"
# Forcer session/cache hors Redis sur petits VPS (évite 500 au submit login)
if grep -qE '^(CACHE_STORE|SESSION_DRIVER)=redis' .env 2>/dev/null; then
    ram_mb="$(awk '/MemTotal:/ {print int($2/1024)}' /proc/meminfo 2>/dev/null || echo 8192)"
    if (( ram_mb <= 4096 )); then
        sed -i 's|^CACHE_STORE=.*|CACHE_STORE=database|' .env 2>/dev/null || true
        if ! grep -q '^CACHE_STORE=' .env; then echo 'CACHE_STORE=database' >> .env; fi
        sed -i 's|^SESSION_DRIVER=.*|SESSION_DRIVER=file|' .env 2>/dev/null || true
        if ! grep -q '^SESSION_DRIVER=' .env; then echo 'SESSION_DRIVER=file' >> .env; fi
        log "CACHE_STORE=database + SESSION_DRIVER=file (RAM ${ram_mb} MiB)"
    fi
fi
sudo -u "${OBIORA_USER}" php artisan optimize:clear 2>/dev/null || true
sudo -u "${OBIORA_USER}" php artisan config:clear
sudo -u "${OBIORA_USER}" php artisan route:clear 2>/dev/null || true
sudo -u "${OBIORA_USER}" php artisan view:clear 2>/dev/null || true
sudo -u "${OBIORA_USER}" php artisan migrate --force 2>/dev/null || true
sudo -u "${OBIORA_USER}" php artisan obiora:post-deploy --skip-migrate 2>/dev/null || true

log "8/9 — Redémarrage services…"
systemctl restart mariadb 2>/dev/null || systemctl restart mysqld 2>/dev/null || true
systemctl restart php-fpm 2>/dev/null || true
systemctl restart nginx 2>/dev/null || true
systemctl restart obiora-queue 2>/dev/null || true
systemctl restart obiora-reverb 2>/dev/null || true

log "9/9 — Vérification…"
sleep 2
if sudo -u "${OBIORA_USER}" php artisan db:show 2>/dev/null | head -5; then
    log "Connexion Laravel OK"
else
    warn "artisan db:show a échoué — vérifier .env et journalctl -u mariadb"
fi

http_code="$(curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1/panel-health 2>/dev/null || echo '000')"
log "panel-health HTTP ${http_code}"
curl -sS http://127.0.0.1/panel-health 2>/dev/null || true
echo ""
login_code="$(curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1/login 2>/dev/null || echo '000')"
log "login HTTP ${login_code}"
df -h / /opt 2>/dev/null || true
log "Terminé — ouvrez le panel (Ctrl+F5). Si 500 : tail -n 80 storage/logs/laravel.log"
