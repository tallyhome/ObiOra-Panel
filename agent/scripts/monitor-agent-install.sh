#!/usr/bin/env bash
# Wrapper install agent ObiOra Monitor — métriques serveur (Phase 3)
set -euo pipefail

PANEL_URL=""
SERVER_ID=""
AGENT_TOKEN=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --panel-url=*) PANEL_URL="${1#*=}"; shift ;;
        --server-id=*) SERVER_ID="${1#*=}"; shift ;;
        --agent-token=*) AGENT_TOKEN="${1#*=}"; shift ;;
        --with-slave) WITH_SLAVE=1; shift ;;
        *) echo "Option inconnue: $1" >&2; exit 1 ;;
    esac
done

if [[ -z "${AGENT_TOKEN}" ]]; then
    echo "ERREUR: --agent-token requis" >&2
    exit 1
fi

if [[ -z "${PANEL_URL}" ]]; then
    echo "ERREUR: --panel-url requis" >&2
    exit 1
fi

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: exécuter en root (sudo)" >&2
    exit 1
fi

TMP_INSTALL="$(mktemp)"
TMP_PUSH="$(mktemp)"
TMP_UNINSTALL="$(mktemp)"
trap 'rm -f "${TMP_INSTALL}" "${TMP_PUSH}" "${TMP_UNINSTALL}"' EXIT

curl -fsSL "${PANEL_URL%/}/install/obiora-metrics-install.sh" -o "${TMP_INSTALL}"
curl -fsSL "${PANEL_URL%/}/install/obiora-metrics-push.sh" -o "${TMP_PUSH}"
curl -fsSL "${PANEL_URL%/}/install/obiora-metrics-uninstall.sh" -o "${TMP_UNINSTALL}" 2>/dev/null || true
chmod +x "${TMP_INSTALL}" "${TMP_PUSH}" "${TMP_UNINSTALL}" 2>/dev/null || chmod +x "${TMP_INSTALL}" "${TMP_PUSH}"

if [[ -z "${SERVER_ID}" ]]; then
    echo "WARN: --server-id absent — l'agent ne pourra pas pousser les métriques sans ID serveur panel" >&2
    SERVER_ID="0"
fi

bash "${TMP_INSTALL}" \
    --panel-url="${PANEL_URL}" \
    --server-id="${SERVER_ID}" \
    --agent-token="${AGENT_TOKEN}" \
    --script-source="${TMP_PUSH}"

if [[ "${WITH_SLAVE:-0}" -eq 1 ]]; then
    export OBIORA_AGENT_TOKEN="${AGENT_TOKEN}"
    SLAVE_TMP="$(mktemp)"
    curl -fsSL "${PANEL_URL%/}/install/slave-agent.sh" -o "${SLAVE_TMP}"
    chmod +x "${SLAVE_TMP}"
    bash "${SLAVE_TMP}" || echo "WARN: install slave optionnel en échec" >&2
    rm -f "${SLAVE_TMP}"
fi
