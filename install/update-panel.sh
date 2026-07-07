#!/usr/bin/env bash
# Mise à jour du panel ObiOra — exécuté en root via sudo (depuis le panel web)
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"
OBIORA_GROUP="${OBIORA_GROUP:-obiora}"
OBIORA_UPDATE_HISTORY_ID="${OBIORA_UPDATE_HISTORY_ID:-}"

# ID historique MAJ passé en 1er argument par PanelUpdater (via sudo)
if [[ -n "${1:-}" ]] && [[ "${1}" =~ ^[0-9]+$ ]]; then
    OBIORA_UPDATE_HISTORY_ID="${1}"
fi

progress() {
    local pct="$1"
    local msg="$2"

    echo "[${pct}%] ${msg}"

    if [[ -n "${OBIORA_UPDATE_HISTORY_ID}" ]] && [[ -f "${OBIORA_INSTALL_DIR}/artisan" ]]; then
        sudo -u "${OBIORA_USER}" php "${OBIORA_INSTALL_DIR}/artisan" obiora:update-progress \
            "${OBIORA_UPDATE_HISTORY_ID}" "${pct}" "${msg}" >/dev/null 2>&1 || true
    fi
}

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: ce script doit être exécuté en root (via sudo ou obiora-panel-update)." >&2
    exit 1
fi

cd "${OBIORA_INSTALL_DIR}"

git config --global --add safe.directory "${OBIORA_INSTALL_DIR}" 2>/dev/null || true

progress 8 "Préparation de la mise à jour…"

echo "[1/8] git fetch..."
progress 12 "Téléchargement depuis GitHub…"
git fetch origin main --tags

echo "[2/8] git sync..."
progress 28 "Synchronisation du code source…"
if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then
    echo "WARN: modifications locales détectées — alignement forcé sur origin/main"
fi
git reset --hard origin/main

# Helper setuid APRÈS git sync (pour récupérer les correctifs avant installation)
if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/common.sh" ]] && [[ -f "${OBIORA_INSTALL_DIR}/install/lib/panel-update-helper.sh" ]]; then
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/panel-update-helper.sh"
    setup_panel_update_helper || echo "WARN: helper MAJ non installé — tentative sudo en fallback"
fi

echo "[2b/8] migrations préliminaires..."
progress 32 "Application des migrations…"
sudo -u "${OBIORA_USER}" php artisan migrate --force 2>/dev/null || true

echo "[3/8] composer..."
progress 42 "Installation des dépendances PHP (composer)…"
sudo -u "${OBIORA_USER}" env PATH=/usr/local/bin:/usr/bin:/bin \
    composer install --no-dev --optimize-autoloader --no-interaction

echo "[4/8] assets frontend..."
if command -v npm &>/dev/null && [[ -f package.json ]]; then
    progress 52 "Installation des dépendances npm…"
    # Toujours réinstaller avant build : évite les erreurs « cannot resolve sweetalert2 »
    # quand package.json a changé mais node_modules est obsolète.
    sudo -u "${OBIORA_USER}" npm ci --ignore-scripts 2>/dev/null \
        || sudo -u "${OBIORA_USER}" npm install --ignore-scripts

    progress 58 "Compilation des assets frontend…"
    sudo -u "${OBIORA_USER}" npm run build
fi

echo "[5/8] artisan migrate..."
progress 72 "Migrations de base de données…"
sudo -u "${OBIORA_USER}" php artisan migrate --force
sudo -u "${OBIORA_USER}" php artisan config:clear

echo "[6/8] artisan optimize..."
progress 82 "Optimisation du panel…"
sudo -u "${OBIORA_USER}" php artisan optimize

echo "[7/8] sudoers agent + répertoire web..."
progress 88 "Configuration des permissions et sudoers…"
if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/common.sh" ]] && [[ -f "${OBIORA_INSTALL_DIR}/install/lib/sudoers.sh" ]]; then
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/sudoers.sh"
    setup_sudoers
fi

# Permissions web
for u in nginx apache www-data; do
    id "${u}" &>/dev/null && usermod -aG "${OBIORA_USER}" "${u}" 2>/dev/null || true
done
chmod 750 "${OBIORA_INSTALL_DIR}"
chmod -R g+rX "${OBIORA_INSTALL_DIR}"
chmod 755 "${OBIORA_INSTALL_DIR}/public"
chmod -R 775 "${OBIORA_INSTALL_DIR}/storage" "${OBIORA_INSTALL_DIR}/bootstrap/cache" 2>/dev/null || true

if id apache &>/dev/null; then
    chown -R "${OBIORA_USER}:apache" "${OBIORA_INSTALL_DIR}/storage" "${OBIORA_INSTALL_DIR}/bootstrap/cache" 2>/dev/null || true
fi

# Relabel SELinux : les nouveaux fichiers ajoutés par git (ex. images, assets)
# n'héritent pas automatiquement du contexte httpd_sys_content_t défini à l'install.
# Sans ce restorecon, Nginx/PHP-FPM reçoivent un "Permission denied" silencieux
# (ex. logo qui ne s'affiche pas comme si le fichier n'existait pas).
if command -v getenforce &>/dev/null && [[ "$(getenforce)" != "Disabled" ]] && command -v restorecon &>/dev/null; then
    restorecon -Rv "${OBIORA_INSTALL_DIR}" >/dev/null 2>&1 || true
fi

echo "[8/8] rechargement des services..."
progress 94 "Rechargement des services (PHP-FPM, Nginx, file d'attente)…"
systemctl reload-or-restart php8.3-fpm 2>/dev/null || systemctl reload-or-restart php-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

# S'assure que le timer du scheduler est bien activé et démarré. Certaines
# installations plus anciennes ou interrompues peuvent se retrouver avec un
# timer présent mais jamais démarré (visible comme "inactive" dans le panel).
if systemctl list-unit-files 2>/dev/null | grep -q '^obiora-scheduler\.timer'; then
    systemctl enable obiora-scheduler.timer >/dev/null 2>&1 || true
    systemctl start obiora-scheduler.timer >/dev/null 2>&1 || true
fi
if systemctl list-unit-files 2>/dev/null | grep -q '^obiora-agent\.service'; then
    systemctl enable obiora-agent >/dev/null 2>&1 || true
    systemctl is-active --quiet obiora-agent || systemctl start obiora-agent >/dev/null 2>&1 || true
fi

# Redémarrage différé du worker de file d'attente (ce script tourne DANS ce
# worker lorsque la MAJ est lancée depuis le panel : un restart immédiat et
# bloquant se tuerait lui-même). On le programme après la fin du script.
if systemctl list-unit-files 2>/dev/null | grep -q '^obiora-queue\.service'; then
    setsid bash -c 'sleep 5; systemctl --no-block restart obiora-queue' >/dev/null 2>&1 </dev/null &
    disown 2>/dev/null || true
fi

progress 100 "Mise à jour terminée avec succès"
echo "OK: panel mis à jour."
