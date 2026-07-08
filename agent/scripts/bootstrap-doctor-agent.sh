#!/usr/bin/env bash
# ObiOra Doctor — agent minimal (sans dépôt ObiOra-Doctor)
# Usage local : sudo OBIORA_PANEL_URL=... OBIORA_SERVER_ID=... OBIORA_AGENT_TOKEN=... bash bootstrap-doctor-agent.sh
# Usage distant : curl -fsSL https://panel/install/doctor-agent.sh | sudo OBIORA_... bash
set -euo pipefail

PANEL_URL="${OBIORA_PANEL_URL:?OBIORA_PANEL_URL requis}"
SERVER_ID="${OBIORA_SERVER_ID:?OBIORA_SERVER_ID requis}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?OBIORA_AGENT_TOKEN requis}"

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n env \
        OBIORA_PANEL_URL="${PANEL_URL}" \
        OBIORA_SERVER_ID="${SERVER_ID}" \
        OBIORA_AGENT_TOKEN="${AGENT_TOKEN}" \
        bash "$0" "$@"
fi

INSTALL_DIR="/opt/obiora-doctor-agent"
API="${PANEL_URL%/}/api/v1/servers/${SERVER_ID}"

mkdir -p "${INSTALL_DIR}"

cat > "${INSTALL_DIR}/run-scan.sh" << 'SCAN'
#!/usr/bin/env bash
set -euo pipefail

PANEL_URL="${OBIORA_PANEL_URL:?}"
SERVER_ID="${OBIORA_SERVER_ID:?}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?}"
API="${PANEL_URL%/}/api/v1/servers/${SERVER_ID}"

hostname="$(hostname -f 2>/dev/null || hostname)"
load="$(awk '{print $1" "$2" "$3}' /proc/loadavg 2>/dev/null || echo '0 0 0')"
mem_total="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
mem_avail="$(awk '/MemAvailable/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
disk_pct="$(df -P / 2>/dev/null | awk 'NR==2 {gsub(/%/,"",$5); print $5}' || echo 0)"
failed_units="$(systemctl --failed --no-legend 2>/dev/null | wc -l | tr -d ' ')"
score=100
[[ "${disk_pct}" -gt 90 ]] && score=$((score - 20))
[[ "${disk_pct}" -gt 95 ]] && score=$((score - 15))
[[ "${failed_units}" -gt 0 ]] && score=$((score - failed_units * 5))
(( score < 0 )) && score=0

generated_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

payload="$(cat <<JSON
{
  "score": ${score},
  "version": "panel-bootstrap-1.0",
  "generated_at": "${generated_at}",
  "host": {"hostname": "${hostname}", "schema_version": "1.0"},
  "results": [
    {"module": "system", "status": "ok", "load": "${load}", "mem_kb": ${mem_avail}, "mem_total_kb": ${mem_total}},
    {"module": "disk", "status": "ok", "root_used_pct": ${disk_pct}},
    {"module": "systemd", "status": "$([[ "${failed_units}" -eq 0 ]] && echo ok || echo warning)", "failed_units": ${failed_units}}
  ]
}
JSON
)"

http_code="$(curl -fsS -o /tmp/obiora-doctor-last.json -w '%{http_code}' \
    -X POST "${API}/diagnostics/reports" \
    -H "Authorization: Bearer ${AGENT_TOKEN}" \
    -H "Content-Type: application/json" \
    -d "${payload}" 2>/dev/null || echo "000")"

if [[ "${http_code}" != "200" ]]; then
    echo "ERREUR: envoi rapport HTTP ${http_code}" >&2
    exit 1
fi

echo "OK: rapport Doctor envoye (score ${score}%)"
SCAN

chmod +x "${INSTALL_DIR}/run-scan.sh"

cat > /etc/systemd/system/obiora-doctor-agent.service << UNIT
[Unit]
Description=ObiOra Doctor agent scan
After=network-online.target

[Service]
Type=oneshot
Environment=OBIORA_PANEL_URL=${PANEL_URL}
Environment=OBIORA_SERVER_ID=${SERVER_ID}
Environment=OBIORA_AGENT_TOKEN=${AGENT_TOKEN}
ExecStart=${INSTALL_DIR}/run-scan.sh
UNIT

cat > /etc/systemd/system/obiora-doctor-agent.timer << 'TIMER'
[Unit]
Description=ObiOra Doctor agent — scan periodique

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
Persistent=true

[Install]
WantedBy=timers.target
TIMER

systemctl daemon-reload
systemctl enable --now obiora-doctor-agent.timer
systemctl start obiora-doctor-agent.service || true

echo "OK: agent Doctor installe — timer obiora-doctor-agent actif (scan / 5 min)"
