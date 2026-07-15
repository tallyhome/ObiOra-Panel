#!/usr/bin/env bash
# Scan securite Obiora — modules Doctor dedies, push vers panel
set -euo pipefail

ENV_FILE="${OBIORA_DOCTOR_ENV:-/opt/obiora-doctor-agent/agent.env}"
DOCTOR_DIR="${OBIORA_DOCTOR_DIR:-/opt/obiora-doctor}"

for env_file in "${ENV_FILE}" /etc/obiora/monitor-agent.env; do
    # shellcheck source=/dev/null
    [[ -f "${env_file}" ]] && source "${env_file}"
done

if [[ -z "${OBIORA_PANEL_URL:-}" && -f /opt/obiora-panel/.env ]]; then
    OBIORA_PANEL_URL="$(grep -E '^APP_URL=' /opt/obiora-panel/.env | head -1 | cut -d= -f2- | tr -d \"'\'')"
    export OBIORA_PANEL_URL
fi

PANEL_URL="${OBIORA_PANEL_URL:?OBIORA_PANEL_URL manquant — installez l'agent Doctor ou vérifiez /opt/obiora-doctor-agent/agent.env}"
SERVER_ID="${OBIORA_SERVER_ID:?OBIORA_SERVER_ID manquant}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?OBIORA_AGENT_TOKEN manquant}"
API="${PANEL_URL%/}/api/v1/servers/${SERVER_ID}"

SECURITY_MODULES="security,obiora,firewall,malware,network,ssl,accounts,persistence,privesc,auth_logs,web_perms,docker_security,mail_dns,waf,hosting_security"
# Lynis exclu du scan periodique (5-15 min) — lancer manuellement: obiora.sh scan --module lynis

run_python_scan() {
    local doctor_bin=""
    if [[ -x "${DOCTOR_DIR}/obiora.sh" ]]; then
        doctor_bin="${DOCTOR_DIR}/obiora.sh"
    elif [[ -f "${DOCTOR_DIR}/bin/obiora-doctor.py" ]]; then
        doctor_bin="python3 ${DOCTOR_DIR}/bin/obiora-doctor.py"
    fi

    if [[ -z "${doctor_bin}" ]]; then
        return 1
    fi

    local modules_args=()
    IFS=',' read -ra MODS <<< "${SECURITY_MODULES}"
    for mod in "${MODS[@]}"; do
        modules_args+=(--module "${mod}")
    done

    # shellcheck disable=SC2086
    payload="$(${doctor_bin} scan "${modules_args[@]}" --json 2>/dev/null)" || return 1

    if [[ -z "${payload}" || "${payload:0:1}" != "{" ]]; then
        return 1
    fi

    echo "${payload}"
    return 0
}

run_bash_fallback() {
    python3 - <<'PY'
import json, subprocess, datetime, socket

def sh(cmd):
    try:
        return subprocess.check_output(cmd, shell=True, stderr=subprocess.DEVNULL, text=True, timeout=30)
    except Exception:
        return ""

hostname = socket.gethostname()
generated_at = datetime.datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ")
ssh_root = "yes" if "PermitRootLogin yes" in sh("grep -E '^PermitRootLogin' /etc/ssh/sshd_config 2>/dev/null") else "no"
ssh_pass = "yes" if "PasswordAuthentication yes" in sh("grep -E '^PasswordAuthentication' /etc/ssh/sshd_config 2>/dev/null") else "no"
fail2ban = "yes" if sh("systemctl is-active fail2ban 2>/dev/null").strip() == "active" else "no"
ufw_active = "yes" if "active" in sh("ufw status 2>/dev/null").lower() else "no"
agent_public = "yes" if sh("ss -tlnp 2>/dev/null | grep -E '0\\.0\\.0\\.0:9100|\\[::\\]:9100'") else "no"

score = 100
findings_sec = []
findings_obiora = []
findings_fw = []

if ssh_root == "yes":
    score -= 25
    findings_sec.append({"level": "CRITICAL", "title": "SSH root login autorise", "details": "PermitRootLogin yes", "recommendation": "Desactiver root SSH"})
if ssh_pass == "yes":
    score -= 15
    findings_sec.append({"level": "WARNING", "title": "Auth SSH mot de passe", "details": "PasswordAuthentication yes", "recommendation": "Desactiver mot de passe SSH"})
if fail2ban == "no":
    score -= 10
    findings_sec.append({"level": "WARNING", "title": "Fail2ban inactif", "details": "Service fail2ban arrete", "recommendation": "Activer fail2ban"})
findings_sec.append({"level": "INFO", "title": "Scan fallback bash", "details": "ObiOra-Doctor Python non installe", "recommendation": "Installer ObiOra-Doctor pour audit complet"})

if agent_public == "yes":
    score -= 30
    findings_obiora.append({"level": "CRITICAL", "title": "Port agent 9100 expose", "details": "Agent accessible publiquement", "recommendation": "Restreindre via firewall"})
else:
    findings_obiora.append({"level": "INFO", "title": "Agent non expose", "details": "Port 9100 non public", "recommendation": "Aucune action"})

if ufw_active == "no":
    score -= 15
    findings_fw.append({"level": "WARNING", "title": "Pare-feu inactif", "details": "UFW/firewalld non actif", "recommendation": "Activer le pare-feu"})
else:
    findings_fw.append({"level": "INFO", "title": "Pare-feu actif", "details": "UFW detecte", "recommendation": "Verifier les regles"})

score = max(0, score)
payload = {
    "score": score,
    "version": "security-fallback-1.0",
    "generated_at": generated_at,
    "host": {"hostname": hostname, "schema_version": "1.0"},
    "results": [
        {"module": "security", "status": "ok", "score": score, "findings": findings_sec},
        {"module": "obiora", "status": "ok", "score": 30 if agent_public == "yes" else 90, "findings": findings_obiora},
        {"module": "firewall", "status": "ok", "score": 40 if ufw_active == "no" else 90, "findings": findings_fw},
    ],
}
print(json.dumps(payload))
PY
}

payload=""
if ! payload="$(run_python_scan)"; then
    payload="$(run_bash_fallback)"
fi

response_file="/tmp/obiora-security-scan.json"
http_code="$(curl -sS -o "${response_file}" -w '%{http_code}' \
    -X POST "${API}/diagnostics/reports" \
    -H "Authorization: Bearer ${AGENT_TOKEN}" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "${payload}" 2>/dev/null || echo "000")"

if [[ "${http_code}" != "200" ]]; then
    echo "ERREUR: envoi rapport securite HTTP ${http_code}" >&2
    [[ -f "${response_file}" ]] && cat "${response_file}" >&2
    exit 1
fi

score="$(echo "${payload}" | python3 -c "import sys,json; print(json.load(sys.stdin).get('score',0))" 2>/dev/null || echo "?")"
echo "OK: scan securite envoye (score ${score}%)"
