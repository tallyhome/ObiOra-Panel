#!/usr/bin/env bash
# ObiOra Panel — aide installation agent Doctor sur un VPS
# Le dossier ObiOra-Doctor doit être présent (clone ou copie SCP).
set -euo pipefail

DOCTOR_DIR="${OBIORA_DOCTOR_DIR:-./ObiOra-Doctor}"

if [[ ! -f "${DOCTOR_DIR}/install/install-agent.sh" ]]; then
    echo "ERREUR: ${DOCTOR_DIR}/install/install-agent.sh introuvable." >&2
    echo "Clonez ObiOra-Doctor à côté de ce script ou définissez OBIORA_DOCTOR_DIR." >&2
    exit 1
fi

export OBIORA_PANEL_URL="${OBIORA_PANEL_URL:-}"
export OBIORA_SERVER_ID="${OBIORA_SERVER_ID:-}"
export OBIORA_AGENT_TOKEN="${OBIORA_AGENT_TOKEN:-}"
export OBIORA_SIGNING_KEY="${OBIORA_SIGNING_KEY:-}"

exec bash "${DOCTOR_DIR}/install/install-agent.sh"
