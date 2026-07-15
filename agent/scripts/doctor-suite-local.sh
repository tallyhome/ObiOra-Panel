#!/usr/bin/env bash
# ObiOra Doctor & Suite — installation LOCAL uniquement (serveur maître du panel).
# Appelé par le worker obiora-queue via sudo NOPASSWD. Ne jamais utiliser via curl.
# Usage : sudo -n /opt/obiora-panel/agent/scripts/doctor-suite-local.sh __obiora_env N KEY=b64 ...
set -euo pipefail

if [[ "${1:-}" == "__obiora_env" ]]; then
    env_count="${2:-0}"
    shift 2

    if [[ ! "${env_count}" =~ ^[0-9]+$ ]]; then
        echo "ERREUR: options d'installation invalides" >&2
        exit 1
    fi

    for ((i = 0; i < env_count; i++)); do
        pair="${1:-}"
        shift || true

        key="${pair%%=*}"
        value_b64="${pair#*=}"

        if [[ ! "${key}" =~ ^OBIORA_(PANEL_URL|SERVER_ID|AGENT_TOKEN|INSTALL_DOCTOR|INSTALL_CRASH_ANALYZER|INSTALL_CRASH_HUNTER|SCRIPT_DIR)$ ]]; then
            echo "ERREUR: option non autorisée: ${key}" >&2
            exit 1
        fi

        value="$(printf '%s' "${value_b64}" | base64 -d 2>/dev/null || true)"
        export "${key}=${value}"
    done
fi

PANEL_URL="${OBIORA_PANEL_URL:?OBIORA_PANEL_URL requis}"
SERVER_ID="${OBIORA_SERVER_ID:?OBIORA_SERVER_ID requis}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?OBIORA_AGENT_TOKEN requis}"

INSTALL_DOCTOR="${OBIORA_INSTALL_DOCTOR:-yes}"
INSTALL_CRASH_ANALYZER="${OBIORA_INSTALL_CRASH_ANALYZER:-yes}"
INSTALL_CRASH_HUNTER="${OBIORA_INSTALL_CRASH_HUNTER:-yes}"
SCRIPT_DIR="${OBIORA_SCRIPT_DIR:?OBIORA_SCRIPT_DIR requis pour installation locale}"

require_script() {
    local name="$1"

    if [[ ! -f "${SCRIPT_DIR}/${name}" ]]; then
        echo "ERREUR: script local introuvable: ${SCRIPT_DIR}/${name}" >&2
        echo "Indice : git pull + chmod +x agent/scripts/*.sh + restart obiora-queue" >&2
        exit 1
    fi
}

echo "=== ObiOra Doctor & Suite — installation locale (sans curl) ==="

export OBIORA_PANEL_URL="${PANEL_URL}"
export OBIORA_SERVER_ID="${SERVER_ID}"
export OBIORA_AGENT_TOKEN="${AGENT_TOKEN}"

if [[ "${INSTALL_DOCTOR}" == "yes" ]]; then
    require_script bootstrap-doctor-agent.sh
    bash "${SCRIPT_DIR}/bootstrap-doctor-agent.sh"
fi

if [[ "${INSTALL_CRASH_ANALYZER}" == "yes" ]]; then
    require_script install-crash-analyzer.sh
    bash "${SCRIPT_DIR}/install-crash-analyzer.sh"
fi

if [[ "${INSTALL_CRASH_HUNTER}" == "yes" ]]; then
    require_script install-crash-hunter.sh
    bash "${SCRIPT_DIR}/install-crash-hunter.sh"
fi

echo "OK: ObiOra Doctor & Suite installés localement (Doctor=${INSTALL_DOCTOR}, CrashAnalyzer=${INSTALL_CRASH_ANALYZER}, CrashHunter=${INSTALL_CRASH_HUNTER})"
