#!/usr/bin/env bash
# Mise à jour du panel ObiOra — exécuté en root via sudo (depuis le panel web)
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"
OBIORA_GROUP="${OBIORA_GROUP:-obiora}"
OBIORA_UPDATE_HISTORY_ID="${OBIORA_UPDATE_HISTORY_ID:-}"
LAST_PROGRESS=8

# ID historique MAJ passé en 1er argument par PanelUpdater (via sudo)
if [[ -n "${1:-}" ]] && [[ "${1}" =~ ^[0-9]+$ ]]; then
    OBIORA_UPDATE_HISTORY_ID="${1}"
fi

progress() {
    local pct="$1"
    local msg="$2"
    LAST_PROGRESS="${pct}"

    echo "[${pct}%] ${msg}"

    if [[ -n "${OBIORA_UPDATE_HISTORY_ID}" ]] && [[ -f "${OBIORA_INSTALL_DIR}/artisan" ]]; then
        sudo -u "${OBIORA_USER}" php "${OBIORA_INSTALL_DIR}/artisan" obiora:update-progress \
            "${OBIORA_UPDATE_HISTORY_ID}" "${pct}" "${msg}" >/dev/null 2>&1 || true
    fi
}

on_update_error() {
    local line="$1"
    local code="$2"
    echo "ERREUR: mise à jour interrompue (ligne ${line}, code ${code})" >&2
    progress "${LAST_PROGRESS}" "Échec — récupération HTTP du panel…"
    finalize_panel_http || true
    exit "${code}"
}

finalize_panel_http() {
    if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/update-recover.sh" ]]; then
        bash "${OBIORA_INSTALL_DIR}/install/lib/update-recover.sh" || true
    else
        sudo -u "${OBIORA_USER}" php artisan up >/dev/null 2>&1 || true
        clear_panel_caches
    fi
}

trap 'on_update_error ${LINENO} $?' ERR

clear_panel_caches() {
    sudo -u "${OBIORA_USER}" php artisan optimize:clear 2>/dev/null || true
    sudo -u "${OBIORA_USER}" php artisan route:clear 2>/dev/null || true
    sudo -u "${OBIORA_USER}" php artisan view:clear 2>/dev/null || true
    sudo -u "${OBIORA_USER}" php artisan config:clear 2>/dev/null || true
}

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: ce script doit être exécuté en root (via sudo ou obiora-panel-update)." >&2
    exit 1
fi

cd "${OBIORA_INSTALL_DIR}"

verify_update_integrity() {
    local rel missing=0
    for rel in \
        install/update-panel.sh \
        install/lib/update-recover.sh \
        install/lib/sudoers.sh \
        install/lib/common.sh; do
        if [[ ! -f "${OBIORA_INSTALL_DIR}/${rel}" ]]; then
            echo "ERREUR: fichier MAJ critique manquant: ${rel}" >&2
            missing=1
        fi
    done
    return "${missing}"
}

if ! verify_update_integrity; then
    progress "${LAST_PROGRESS}" "Échec — fichiers MAJ critiques manquants"
    exit 1
fi

# Git ne conserve pas toujours le bit +x : réappliquer avant toute opération
chmod +x "${OBIORA_INSTALL_DIR}/install/update-panel.sh" 2>/dev/null || true
chmod +x "${OBIORA_INSTALL_DIR}"/install/*.sh 2>/dev/null || true
chmod +x "${OBIORA_INSTALL_DIR}"/install/lib/*.sh 2>/dev/null || true

# Récupération d'urgence : un drop-in bind-address ObiOra peut avoir empêché
# MariaDB de redémarrer (panel en 500 / Connection refused sur 127.0.0.1:3306).
if [[ -f "${OBIORA_INSTALL_DIR}/agent/scripts/mysql-docker-recover.sh" ]]; then
    bash "${OBIORA_INSTALL_DIR}/agent/scripts/mysql-docker-recover.sh" || true
fi

git config --global --add safe.directory "${OBIORA_INSTALL_DIR}" 2>/dev/null || true

progress 8 "Préparation de la mise à jour…"

echo "[1/8] git fetch..."
progress 12 "Téléchargement depuis GitHub…"
git fetch origin main --tags

TARGET_VERSION="${2:-}"
TARGET_VERSION="${TARGET_VERSION#v}"

echo "[2/8] git sync..."
progress 28 "Synchronisation du code source…"
if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then
    echo "WARN: modifications locales détectées — alignement forcé sur la release cible"
fi

checkout_target_release() {
    local tag="$1"

    if [[ -z "${tag}" ]]; then
        tag="$(git tag -l 'v*' --sort=-v:refname 2>/dev/null | head -1 || true)"
        tag="${tag#v}"
    fi

    if [[ -z "${tag}" ]]; then
        echo "WARN: aucun tag semver — fallback origin/main"
        git reset --hard origin/main
        return
    fi

    local ref="v${tag}"
    if git checkout -f "${ref}" 2>/dev/null; then
        echo "OK: code aligné sur ${ref}"
        if [[ -f VERSION ]]; then
            echo "INFO: VERSION=$(tr -d ' \n\r' < VERSION)"
        fi
        return
    fi

    echo "WARN: tag ${ref} introuvable — fallback origin/main"
    git reset --hard origin/main
}

checkout_target_release "${TARGET_VERSION}"

# git checkout remet obiOra-agent en 644 (non exécutable) — corriger avant services
if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/common.sh" ]]; then
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
    ensure_agent_executables
fi

# git checkout en root laisse des fichiers root:root — npm/vite (utilisateur obiora)
# ne peut alors pas vider public/build (rimraf EACCES).
chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}"

# Évite les 500 pendant la MAJ : routes/views/config cachés ≠ nouveau code (Phase 13+).
echo "[2a/8] purge caches Laravel…"
progress 30 "Purge des caches (routes, vues)…"
clear_panel_caches

# Helper setuid APRÈS git sync (pour récupérer les correctifs avant installation)
if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/common.sh" ]] && [[ -f "${OBIORA_INSTALL_DIR}/install/lib/panel-update-helper.sh" ]]; then
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/panel-update-helper.sh"
    setup_panel_update_helper || echo "WARN: helper MAJ non installé — tentative sudo en fallback"
fi

echo "[3/8] composer..."
progress 42 "Installation des dépendances PHP (composer)…"
sudo -u "${OBIORA_USER}" env PATH=/usr/local/bin:/usr/bin:/bin \
    composer install --no-dev --optimize-autoloader --no-interaction

echo "[3b/8] migrations base de données…"
progress 46 "Application des migrations…"
sudo -u "${OBIORA_USER}" php artisan migrate --force

echo "[4/8] assets frontend..."
if command -v npm &>/dev/null && [[ -f package.json ]]; then
    prepare_frontend_build_dir() {
        local build_dir="${OBIORA_INSTALL_DIR}/public/build"
        rm -rf "${build_dir}"
        mkdir -p "${build_dir}"
        chown "${OBIORA_USER}:${OBIORA_GROUP}" "${build_dir}"
        chmod 775 "${build_dir}"
    }

    restore_frontend_build_backup() {
        local build_dir="${OBIORA_INSTALL_DIR}/public/build"
        local backup_dir="$1"

        if [[ -z "${backup_dir}" ]] || [[ ! -d "${backup_dir}" ]]; then
            return 1
        fi

        rm -rf "${build_dir}"
        mkdir -p "${build_dir}"
        cp -a "${backup_dir}/." "${build_dir}/"
        chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${build_dir}"
        chmod -R 775 "${build_dir}"
        echo "WARN: assets frontend restaurés depuis la sauvegarde (npm build en échec)"
    }

    BUILD_BACKUP_DIR=""
    if [[ -f "${OBIORA_INSTALL_DIR}/public/build/manifest.json" ]]; then
        BUILD_BACKUP_DIR="$(mktemp -d)"
        cp -a "${OBIORA_INSTALL_DIR}/public/build/." "${BUILD_BACKUP_DIR}/"
    fi

    prepare_frontend_build_dir

    progress 52 "Installation des dépendances npm…"
    if ! sudo -u "${OBIORA_USER}" env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" \
        npm ci --ignore-scripts 2>/dev/null \
        && ! sudo -u "${OBIORA_USER}" env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" npm install --ignore-scripts; then
        restore_frontend_build_backup "${BUILD_BACKUP_DIR}" || true
        rm -rf "${BUILD_BACKUP_DIR}"
        echo "ERREUR: installation npm échouée" >&2
        exit 1
    fi

    progress 58 "Compilation des assets frontend…"
    NPM_BUILD_OK=0
    if command -v timeout &>/dev/null; then
        if sudo -u "${OBIORA_USER}" env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" \
            timeout 900 npm run build; then
            NPM_BUILD_OK=1
        fi
    elif sudo -u "${OBIORA_USER}" env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" npm run build; then
        NPM_BUILD_OK=1
    fi

    if [[ "${NPM_BUILD_OK}" -ne 1 ]]; then
        restore_frontend_build_backup "${BUILD_BACKUP_DIR}" || true
        rm -rf "${BUILD_BACKUP_DIR}"
        echo "ERREUR: compilation frontend échouée" >&2
        exit 1
    fi

    rm -rf "${BUILD_BACKUP_DIR}"
    if [[ -f "${OBIORA_INSTALL_DIR}/VERSION" ]]; then
        install -d -m 775 -o "${OBIORA_USER}" -g "${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/storage/app"
        cp "${OBIORA_INSTALL_DIR}/VERSION" "${OBIORA_INSTALL_DIR}/storage/app/.frontend-build-version"
        chown "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/storage/app/.frontend-build-version" 2>/dev/null || true
    fi
fi

echo "[5/8] artisan post-deploy (RBAC, politiques d'alerte, caches, scripts agent)…"
progress 72 "RBAC, politiques d'alerte et caches…"
sudo -u "${OBIORA_USER}" php artisan obiora:post-deploy --skip-migrate
sudo -u "${OBIORA_USER}" php artisan obiora:setup-site-api --ensure --quiet-output 2>/dev/null || true
clear_panel_caches

echo "[6/8] purge caches finales…"
progress 82 "Purge des caches Laravel…"
clear_panel_caches

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

if command -v getenforce &>/dev/null && [[ "$(getenforce)" != "Disabled" ]] && command -v restorecon &>/dev/null; then
    restorecon -Rv "${OBIORA_INSTALL_DIR}" >/dev/null 2>&1 || true
fi

echo "[8/8] finalisation HTTP du panel..."
progress 94 "Finalisation (caches, PHP-FPM, Nginx)…"

# Ne pas « restart » PHP-FPM ici : ça coupe les requêtes Livewire (502) pendant la MAJ.
# Le reload gracieux est fait dans finalize_panel_http.
if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/reverb.sh" ]]; then
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/reverb.sh"
    setup_reverb
    append_reverb_nginx
fi

systemctl reload nginx 2>/dev/null || true

if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/scheduler.sh" ]]; then
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/scheduler.sh"
    ensure_panel_scheduler || true
fi
if systemctl list-unit-files 2>/dev/null | grep -q '^obiora-agent\.service'; then
    if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/common.sh" ]]; then
        # shellcheck source=/dev/null
        source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
        ensure_agent_executables
    fi
    if [[ -f "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" ]]; then
        sed "s|/opt/obiora-panel|${OBIORA_INSTALL_DIR}|g" \
            "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" \
            > /etc/systemd/system/obiora-agent.service
        systemctl daemon-reload 2>/dev/null || true
    fi
    systemctl enable obiora-agent >/dev/null 2>&1 || true
    systemctl restart obiora-agent >/dev/null 2>&1 || systemctl start obiora-agent >/dev/null 2>&1 || true
fi

# Redémarrage différé du worker — géré par PanelUpdater après succès du job
# (un restart ici tuait le job MAJ en cours dans obiora-queue).

if systemctl list-unit-files 2>/dev/null | grep -q '^obiora-reverb\.service'; then
    systemctl restart obiora-reverb >/dev/null 2>&1 || true
fi

if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/systemd.sh" ]]; then
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/systemd.sh"
    ensure_boot_service_order || true
fi

finalize_panel_http

trap - ERR
progress 100 "Mise à jour terminée avec succès"
echo "OK: panel mis a jour."
