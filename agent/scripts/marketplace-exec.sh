#!/usr/bin/env bash
# Exécute un script marketplace (install/uninstall) en root via agent/scripts sudoers
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
SCRIPT="${1:-}"

if [[ -z "${SCRIPT}" || ! -f "${SCRIPT}" ]]; then
    echo "ERREUR: script introuvable" >&2
    exit 1
fi

real_script="$(readlink -f "${SCRIPT}" 2>/dev/null || realpath "${SCRIPT}" 2>/dev/null || echo "${SCRIPT}")"

if [[ ! "${real_script}" =~ ^${OBIORA_INSTALL_DIR}/packages/[^/]+/(install|uninstall)\.sh$ ]]; then
    echo "ERREUR: chemin non autorisé : ${real_script}" >&2
    exit 1
fi

shift
exec bash "${real_script}" "$@"
