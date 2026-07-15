#!/usr/bin/env bash
# ObiOra Doctor — agent minimal (sans dépôt ObiOra-Doctor)
# Usage local panel : sudo -n /opt/obiora-panel/agent/scripts/bootstrap-doctor-agent.sh __obiora_env 3 ...
# Usage distant : curl -fsSL https://panel/install/doctor-agent.sh | sudo OBIORA_... bash
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

        if [[ ! "${key}" =~ ^OBIORA_(PANEL_URL|SERVER_ID|AGENT_TOKEN)$ ]]; then
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

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n "$0" "$@"
fi

INSTALL_DIR="/opt/obiora-doctor-agent"
ENV_FILE="${INSTALL_DIR}/agent.env"

mkdir -p "${INSTALL_DIR}"

cat > "${ENV_FILE}" <<ENV
OBIORA_PANEL_URL=${PANEL_URL}
OBIORA_SERVER_ID=${SERVER_ID}
OBIORA_AGENT_TOKEN=${AGENT_TOKEN}
ENV
chmod 600 "${ENV_FILE}"

cat > "${INSTALL_DIR}/run-scan.sh" << 'SCAN'
#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="/opt/obiora-doctor-agent/agent.env"
# shellcheck source=/dev/null
[[ -f "${ENV_FILE}" ]] && source "${ENV_FILE}"

PANEL_URL="${OBIORA_PANEL_URL:?OBIORA_PANEL_URL manquant}"
SERVER_ID="${OBIORA_SERVER_ID:?OBIORA_SERVER_ID manquant}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?OBIORA_AGENT_TOKEN manquant}"
API="${PANEL_URL%/}/api/v1/servers/${SERVER_ID}"

hostname="$(hostname -f 2>/dev/null || hostname)"
load="$(awk '{print $1" "$2" "$3}' /proc/loadavg 2>/dev/null || echo '0 0 0')"
mem_total="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
mem_avail="$(awk '/MemAvailable/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
disk_pct="$(df -P / 2>/dev/null | awk 'NR==2 {gsub(/%/,"",$5); print $5}' || echo 0)"
failed_units="$(systemctl --failed --no-legend 2>/dev/null | wc -l | tr -d ' ')"
failed_unit_names="$(systemctl --failed --no-legend --plain 2>/dev/null | awk '{print $1}' | head -10 | paste -sd ',' - || true)"
score=100
if [[ "${disk_pct}" -gt 90 ]]; then score=$((score - 20)); fi
if [[ "${disk_pct}" -gt 95 ]]; then score=$((score - 15)); fi
if [[ "${failed_units}" -gt 0 ]]; then score=$((score - failed_units * 5)); fi
if (( score < 0 )); then score=0; fi

if [[ "${failed_units}" -eq 0 ]]; then
    systemd_status="ok"
else
    systemd_status="warning"
fi

generated_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
response_file="/tmp/obiora-doctor-last.json"

payload="$(cat <<JSON
{
  "score": ${score},
  "version": "bootstrap-1.0",
  "generated_at": "${generated_at}",
  "host": {"hostname": "${hostname}", "schema_version": "1.0"},
  "results": [
    {"module": "system", "status": "ok", "load": "${load}", "mem_kb": ${mem_avail}, "mem_total_kb": ${mem_total}},
    {"module": "disk", "status": "ok", "root_used_pct": ${disk_pct}},
    {"module": "systemd", "status": "${systemd_status}", "failed_units": ${failed_units}, "failed_unit_names": "${failed_unit_names}"}
  ]
}
JSON
)"

http_code="$(curl -sS -o "${response_file}" -w '%{http_code}' \
    -X POST "${API}/diagnostics/reports" \
    -H "Authorization: Bearer ${AGENT_TOKEN}" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "${payload}" 2>/dev/null || echo "000")"

if [[ "${http_code}" != "200" ]]; then
    echo "ERREUR: envoi rapport HTTP ${http_code} vers ${API}/diagnostics/reports" >&2
    if [[ -f "${response_file}" ]]; then
        cat "${response_file}" >&2
    fi
    exit 1
fi

echo "OK: rapport Doctor envoye (score ${score}%)"
SCAN

chmod +x "${INSTALL_DIR}/run-scan.sh"

cat > /etc/systemd/system/obiora-doctor-agent.service << UNIT
[Unit]
Description=ObiOra Doctor agent scan
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
EnvironmentFile=${ENV_FILE}
ExecStart=${INSTALL_DIR}/run-scan.sh
StandardOutput=journal
StandardError=journal
UNIT

cat > /etc/systemd/system/obiora-doctor-agent.timer << TIMER
[Unit]
Description=ObiOra Doctor agent — scan periodique

[Timer]
Unit=obiora-doctor-agent.service
OnBootSec=2min
OnUnitActiveSec=5min
Persistent=true

[Install]
WantedBy=timers.target
TIMER

systemctl daemon-reload
systemctl enable --now obiora-doctor-agent.timer

echo "Test du premier scan…"
if ! "${INSTALL_DIR}/run-scan.sh"; then
    echo "ERREUR: le premier scan a echoue. Details :" >&2
    journalctl -u obiora-doctor-agent.service -n 15 --no-pager >&2 || true
    exit 1
fi

echo "OK: agent Doctor installe — timer obiora-doctor-agent actif (scan / 5 min)"
