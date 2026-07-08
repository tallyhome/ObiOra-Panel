#!/usr/bin/env bash
# Active l'API démo ObiOra-SiteWeb sur le Panel (une commande, root ou utilisateur obiora)
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"

cd "${OBIORA_INSTALL_DIR}"

run_artisan() {
    if [[ "${EUID}" -eq 0 ]]; then
        sudo -u "${OBIORA_USER}" php artisan "$@"
    else
        php artisan "$@"
    fi
}

echo "=== ObiOra — activation API démo SiteWeb ==="

run_artisan obiora:post-deploy --skip-migrate
run_artisan obiora:setup-site-api --ensure
run_artisan config:clear
run_artisan route:clear
run_artisan obiora:site-api-status

echo ""
echo "Copiez OBIORA_PANEL_API_KEY (affichée ci-dessus) dans ObiOra-SiteWeb/.env"
echo "Puis sur le SiteWeb : php artisan config:clear && php artisan obiora:check-panel"
