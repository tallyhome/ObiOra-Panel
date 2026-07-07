#!/usr/bin/env bash
# Exécute un script marketplace (install/uninstall) en root via agent/scripts sudoers
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"
SCRIPT="${1:-}"
PROGRESS_KEY="${2:-}"
ACTION_LABEL="${3:-install}"

progress() {
    local pct="$1"
    local msg="$2"

    echo "[${pct}%] ${msg}"

    if [[ -n "${PROGRESS_KEY}" ]] && [[ -f "${OBIORA_INSTALL_DIR}/artisan" ]]; then
        sudo -u "${OBIORA_USER}" php "${OBIORA_INSTALL_DIR}/artisan" obiora:progress \
            "marketplace:${PROGRESS_KEY}" "${pct}" "${msg}" >/dev/null 2>&1 || true
    fi
}

if [[ -z "${SCRIPT}" || ! -f "${SCRIPT}" ]]; then
    echo "ERREUR: script introuvable" >&2
    exit 1
fi

real_script="$(readlink -f "${SCRIPT}" 2>/dev/null || realpath "${SCRIPT}" 2>/dev/null || echo "${SCRIPT}")"

if [[ ! "${real_script}" =~ ^${OBIORA_INSTALL_DIR}/packages/[^/]+/(install|uninstall)\.sh$ ]]; then
    echo "ERREUR: chemin non autorisé : ${real_script}" >&2
    exit 1
fi

shift 3 2>/dev/null || shift $#

progress 10 "Préparation ${ACTION_LABEL}…"
progress 25 "Exécution du script (peut prendre plusieurs minutes)…"

if bash "${real_script}" "$@"; then
    progress 90 "Script terminé, finalisation…"
    exit 0
fi

progress 100 "Échec ${ACTION_LABEL}"
exit 1
