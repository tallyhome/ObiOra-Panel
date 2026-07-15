#!/usr/bin/env bash
# ObiOra Doctor & Suite — installation unifiée (Doctor + Crash Analyzer + CrashHunter)
# Usage distant: curl -fsSL https://panel/install/doctor-suite.sh | sudo OBIORA_PANEL_URL=... bash
# Usage local panel: sudo -n /opt/obiora-panel/agent/scripts/install-doctor-suite.sh __obiora_env N KEY=b64 ...
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

SCRIPT_DIR="${OBIORA_SCRIPT_DIR:-/tmp}"
if [[ "${SCRIPT_DIR}" == "/tmp" && -n "${BASH_SOURCE[0]:-}" && "${BASH_SOURCE[0]}" != bash ]]; then
    _candidate="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ -f "${_candidate}/bootstrap-doctor-agent.sh" ]]; then
        SCRIPT_DIR="${_candidate}"
    fi
fi

echo "=== ObiOra Doctor & Suite — installation ==="

export OBIORA_PANEL_URL="${PANEL_URL}"
export OBIORA_SERVER_ID="${SERVER_ID}"
export OBIORA_AGENT_TOKEN="${AGENT_TOKEN}"

if [[ "${INSTALL_DOCTOR}" == "yes" ]]; then
    if [[ -f "${SCRIPT_DIR}/bootstrap-doctor-agent.sh" ]]; then
        bash "${SCRIPT_DIR}/bootstrap-doctor-agent.sh"
    elif curl -fsSL "${PANEL_URL%/}/install/doctor-agent.sh" -o /tmp/obiora-doctor-install.sh 2>/dev/null; then
        bash /tmp/obiora-doctor-install.sh
        rm -f /tmp/obiora-doctor-install.sh
    else
        echo "ERREUR: impossible de récupérer le script Doctor" >&2
        exit 1
    fi
fi

if [[ "${INSTALL_CRASH_ANALYZER}" == "yes" ]]; then
    if [[ -f "${SCRIPT_DIR}/install-crash-analyzer.sh" ]]; then
        bash "${SCRIPT_DIR}/install-crash-analyzer.sh"
    elif curl -fsSL "${PANEL_URL%/}/install/crash-analyzer.sh" -o /tmp/obiora-crash-install.sh 2>/dev/null; then
        bash /tmp/obiora-crash-install.sh
        rm -f /tmp/obiora-crash-install.sh
    else
        echo "AVERTISSEMENT: Crash Analyzer non installé (script introuvable)" >&2
    fi
fi

if [[ "${INSTALL_CRASH_HUNTER}" == "yes" ]]; then
    if [[ -f "${SCRIPT_DIR}/install-crash-hunter.sh" ]]; then
        bash "${SCRIPT_DIR}/install-crash-hunter.sh"
    elif curl -fsSL "${PANEL_URL%/}/install/crash-hunter.sh" -o /tmp/obiora-crashhunter-install.sh 2>/dev/null; then
        bash /tmp/obiora-crashhunter-install.sh
        rm -f /tmp/obiora-crashhunter-install.sh
    else
        echo "AVERTISSEMENT: CrashHunter non installé (script introuvable)" >&2
    fi
fi

echo "OK: ObiOra Doctor & Suite installés (Doctor=${INSTALL_DOCTOR}, CrashAnalyzer=${INSTALL_CRASH_ANALYZER}, CrashHunter=${INSTALL_CRASH_HUNTER})"
