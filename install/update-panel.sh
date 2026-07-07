#!/usr/bin/env bash
# Mise à jour du panel ObiOra — exécuté en root via sudo (depuis le panel web)
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: ce script doit être exécuté en root (via sudo)." >&2
    exit 1
fi

cd "${OBIORA_INSTALL_DIR}"

git config --global --add safe.directory "${OBIORA_INSTALL_DIR}" 2>/dev/null || true

echo "[1/7] git fetch..."
git fetch origin main --tags

echo "[2/7] git sync..."
if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then
    echo "WARN: modifications locales détectées — alignement forcé sur origin/main"
fi
git reset --hard origin/main

echo "[3/7] composer..."
sudo -u "${OBIORA_USER}" env PATH=/usr/local/bin:/usr/bin:/bin \
    composer install --no-dev --optimize-autoloader --no-interaction

echo "[4/7] assets frontend..."
if command -v npm &>/dev/null && [[ -f package.json ]]; then
    if [[ ! -f public/build/manifest.json ]]; then
        sudo -u "${OBIORA_USER}" npm ci --omit=dev 2>/dev/null || sudo -u "${OBIORA_USER}" npm install
        sudo -u "${OBIORA_USER}" npm run build
    else
        # Rebuild si SCSS/JS modifiés dans le dernier pull
        if git diff HEAD@{1} HEAD --name-only 2>/dev/null | grep -qE '(resources/|package.*\.json|vite\.config)'; then
            sudo -u "${OBIORA_USER}" npm run build
        fi
    fi
fi

echo "[5/7] artisan migrate..."
sudo -u "${OBIORA_USER}" php artisan migrate --force
sudo -u "${OBIORA_USER}" php artisan config:clear

echo "[6/7] artisan optimize..."
sudo -u "${OBIORA_USER}" php artisan optimize

echo "[7/8] sudoers agent + répertoire web..."
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

echo "[8/8] rechargement des services..."
systemctl reload-or-restart php8.3-fpm 2>/dev/null || systemctl reload-or-restart php-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

# Redémarrage différé du worker de file d'attente (ce script tourne DANS ce
# worker lorsque la MAJ est lancée depuis le panel : un restart immédiat et
# bloquant se tuerait lui-même). On le programme après la fin du script.
if systemctl list-unit-files 2>/dev/null | grep -q '^obiora-queue\.service'; then
    setsid bash -c 'sleep 5; systemctl --no-block restart obiora-queue' >/dev/null 2>&1 </dev/null &
    disown 2>/dev/null || true
fi

echo "OK: panel mis à jour."
