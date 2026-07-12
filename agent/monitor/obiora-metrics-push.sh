#!/usr/bin/env bash
# ObiOra Monitor Agent — collecte métriques 1 min + push HTTPS (inspiré patterns Pinguzo)
set -euo pipefail

AGENT_VERSION="1.0.0"
SCHEMA_VERSION=1

ENV_FILE="${OBIORA_MONITOR_ENV:-/etc/obiora/monitor-agent.env}"
LOCK_FILE="/var/run/obiora-metrics-agent.lock"
LOCK_MAX_AGE=300
LOG_FILE="${OBIORA_METRICS_LOG:-/var/log/obiora/metrics-agent.log}"
QUEUE_DIR="${OBIORA_METRICS_QUEUE:-/var/lib/obiora/metrics-queue}"
DAILY_STAMP="${QUEUE_DIR}/daily.stamp"
MAX_QUEUE_FILES=1440

if [[ -f "${ENV_FILE}" ]]; then
    # shellcheck source=/dev/null
    source "${ENV_FILE}"
fi

PANEL_URL="${OBIORA_PANEL_URL:-}"
SERVER_ID="${OBIORA_SERVER_ID:-}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:-}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "${LOG_FILE}" 2>&1
}

apply_lock() {
    mkdir -p "$(dirname "${LOCK_FILE}")" 2>/dev/null || true

    if [[ -f "${LOCK_FILE}" ]]; then
        local old_pid
        old_pid="$(cat "${LOCK_FILE}" 2>/dev/null || true)"

        if [[ -n "${old_pid}" ]] && kill -0 "${old_pid}" 2>/dev/null; then
            local proc_start now age
            proc_start="$(stat -c %Y "${LOCK_FILE}" 2>/dev/null || echo 0)"
            now="$(date +%s)"
            age=$(( now - proc_start ))

            if [[ "${age}" -lt "${LOCK_MAX_AGE}" ]]; then
                exit 0
            fi

            log "WARN: killing stuck metrics agent PID ${old_pid} (${age}s)"
            kill -9 "${old_pid}" 2>/dev/null || true
            sleep 1
        fi
    fi

    echo $$ > "${LOCK_FILE}"
}

release_lock() {
    rm -f "${LOCK_FILE}" 2>/dev/null || true
}

trap 'release_lock' EXIT

mkdir -p "$(dirname "${LOG_FILE}")" "${QUEUE_DIR}" 2>/dev/null || true

if [[ -z "${AGENT_TOKEN}" || -z "${PANEL_URL}" || -z "${SERVER_ID}" ]]; then
    log "ERROR: OBIORA_PANEL_URL, OBIORA_SERVER_ID et OBIORA_AGENT_TOKEN requis (${ENV_FILE})"
    exit 1
fi

apply_lock

METRICS_ENDPOINT="${PANEL_URL%/}/api/v1/servers/${SERVER_ID}/monitor/metrics"

read_cpu_stats() {
    local line
    line="$(grep -m1 '^cpu ' /proc/stat 2>/dev/null || true)"
    if [[ -z "${line}" ]]; then
        echo "0 0"
        return
    fi
    local user nice system idle iowait irq softirq steal _rest
    read -r _ user nice system idle iowait irq softirq steal _rest <<< "${line}"
    local total busy
    total=$(( user + nice + system + idle + iowait + irq + softirq + steal ))
    busy=$(( total - idle - iowait ))
    if [[ "${total}" -le 0 ]]; then
        echo "0 0"
        return
    fi
    local cpu_pct steal_pct
    cpu_pct="$(awk "BEGIN {printf \"%.2f\", (${busy}/${total})*100}")"
    steal_pct="$(awk "BEGIN {printf \"%.2f\", (${steal:-0}/${total})*100}")"
    echo "${cpu_pct} ${steal_pct}"
}

read_memory() {
    local mem_total mem_avail swap_total swap_free
    mem_total="$(awk '/^MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
    mem_avail="$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
    swap_total="$(awk '/^SwapTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
    swap_free="$(awk '/^SwapFree:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
    local mem_pct=0 swap_pct=0
    if [[ "${mem_total}" -gt 0 ]]; then
        mem_pct="$(awk "BEGIN {printf \"%.2f\", ((${mem_total}-${mem_avail})/${mem_total})*100}")"
    fi
    if [[ "${swap_total}" -gt 0 ]]; then
        swap_pct="$(awk "BEGIN {printf \"%.2f\", ((${swap_total}-${swap_free})/${swap_total})*100}")"
    fi
    echo "${mem_pct} ${swap_pct}"
}

read_disk_max() {
    df -P -x tmpfs -x devtmpfs -x squashfs 2>/dev/null | awk 'NR>1 {
        gsub("%","",$5); if ($5+0 > max) max=$5+0
    } END { if (max=="") print "0"; else printf "%.2f", max+0 }'
}

read_load() {
    awk '{print $1" "$2" "$3}' /proc/loadavg 2>/dev/null || echo "0 0 0"
}

read_uptime() {
    awk '{print int($1)}' /proc/uptime 2>/dev/null || echo 0
}

read_network_json() {
    awk 'NR>2 && $1 !~ /^(lo:|face)/ {
        gsub(":","",$1);
        printf "\"%s\":{\"rx\":%s,\"tx\":%s},", $1, $2, $10
    }' /proc/net/dev 2>/dev/null | sed 's/,$//' || true
}

read_partitions_json() {
    df -P -x tmpfs -x devtmpfs 2>/dev/null | awk 'NR>1 {
        gsub("%","",$5);
        printf "\"%s\":{\"mount\":\"%s\",\"used_percent\":%s},", $6, $6, $5+0
    }' | sed 's/,$//' || true
}

read_top_processes_json() {
    ps -eo pid,pcpu,pmem,comm --sort=-pcpu 2>/dev/null | awk 'NR>1 && NR<=101 {
        gsub(/"/,"\\\"", $4);
        printf "{\"pid\":%s,\"cpu\":%s,\"mem\":%s,\"name\":\"%s\"},", $1, $2, $3, $4
    }' | sed 's/,$//' || true
}

read_daily_info_json() {
    local os_name os_version kernel arch primary_ip
    os_name="$(. /etc/os-release 2>/dev/null && echo "${NAME:-Linux}" || echo Linux)"
    os_version="$(. /etc/os-release 2>/dev/null && echo "${VERSION_ID:-}" || true)"
    kernel="$(uname -r 2>/dev/null || true)"
    arch="$(uname -m 2>/dev/null || true)"
    primary_ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"

    printf '{"os_name":"%s","os_version":"%s","kernel":"%s","architecture":"%s","primary_ip":"%s"}' \
        "${os_name}" "${os_version}" "${kernel}" "${arch}" "${primary_ip}"
}

build_payload() {
    local cpu_line mem_line load_line cpu_pct steal_pct mem_pct swap_pct disk_pct load_1 load_5 load_15 uptime
    cpu_line="$(read_cpu_stats)"
    read -r cpu_pct steal_pct <<< "${cpu_line}"
    mem_line="$(read_memory)"
    read -r mem_pct swap_pct <<< "${mem_line}"
    disk_pct="$(read_disk_max)"
    load_line="$(read_load)"
    read -r load_1 load_5 load_15 <<< "${load_line}"
    uptime="$(read_uptime)"

    local net_json parts_json procs_json extra=""
    net_json="$(read_network_json)"
    parts_json="$(read_partitions_json)"
    procs_json="$(read_top_processes_json)"

    local daily_block=""
    if [[ ! -f "${DAILY_STAMP}" ]] || [[ "$(date +%Y%m%d)" != "$(cat "${DAILY_STAMP}" 2>/dev/null || true)" ]]; then
        daily_block=",\"daily_info\":$(read_daily_info_json)"
        date +%Y%m%d > "${DAILY_STAMP}" 2>/dev/null || true
    fi

    cat <<JSON
{"schema_version":${SCHEMA_VERSION},"agent_version":"${AGENT_VERSION}","sampled_at":$(date +%s),"cpu_percent":${cpu_pct},"cpu_steal_percent":${steal_pct},"memory_percent":${mem_pct},"swap_percent":${swap_pct},"disk_percent":${disk_pct},"load_1":${load_1},"load_5":${load_5},"load_15":${load_15},"uptime_seconds":${uptime},"payload":{"network":{${net_json}},"partitions":{${parts_json}},"processes":[${procs_json}]}${daily_block}}
JSON
}

post_payload() {
    local body="$1"
    local http_code
    http_code="$(curl -fsS -o /tmp/obiora-metrics-resp.json -w '%{http_code}' \
        -X POST "${METRICS_ENDPOINT}" \
        -H "Authorization: Bearer ${AGENT_TOKEN}" \
        -H "Content-Type: application/json" \
        --connect-timeout 15 \
        --max-time 45 \
        -d "${body}" 2>/dev/null || echo "000")"

    if [[ "${http_code}" =~ ^2 ]]; then
        return 0
    fi

    return 1
}

enqueue_payload() {
    local body="$1"
    local count
    count="$(find "${QUEUE_DIR}" -maxdepth 1 -type f -name '*.json' 2>/dev/null | wc -l | tr -d ' ')"
    if [[ "${count}" -ge "${MAX_QUEUE_FILES}" ]]; then
        find "${QUEUE_DIR}" -maxdepth 1 -type f -name '*.json' -printf '%T+ %p\n' 2>/dev/null \
            | sort | head -n 1 | awk '{print $2}' | xargs -r rm -f
    fi
    local file="${QUEUE_DIR}/$(date +%s)-$$.json"
    printf '%s' "${body}" > "${file}"
}

flush_queue() {
    local sent=0
    local files
    mapfile -t files < <(find "${QUEUE_DIR}" -maxdepth 1 -type f -name '*.json' 2>/dev/null | sort)
    for f in "${files[@]}"; do
        [[ -f "${f}" ]] || continue
        local body
        body="$(cat "${f}")"
        if post_payload "${body}"; then
            rm -f "${f}"
            sent=$(( sent + 1 ))
        else
            break
        fi
    done
    if [[ "${sent}" -gt 0 ]]; then
        log "INFO: Sent ${sent} queued metric(s) as a batch"
    fi
}

main() {
    flush_queue
    local body
    body="$(build_payload)"
    if post_payload "${body}"; then
        log "INFO: Metrics pushed"
    else
        enqueue_payload "${body}"
        log "WARN: Failed to send metrics, queued for retry"
    fi
}

main "$@"
