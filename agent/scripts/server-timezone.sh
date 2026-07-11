#!/usr/bin/env bash
# Fuseau horaire et horloge système — local ou agent slave
set -euo pipefail

ACTION="${1:-status}"
TZ_NAME="${2:-}"

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        echo "ERREUR: privilèges root requis" >&2
        exit 1
    fi
}

validate_tz() {
    local tz="$1"
    if [[ -z "${tz}" ]]; then
        echo "ERREUR: fuseau horaire vide" >&2
        exit 1
    fi
    if [[ ! "${tz}" =~ ^[A-Za-z0-9_+-]+(/[A-Za-z0-9_+-]+)+$ ]]; then
        echo "ERREUR: fuseau horaire invalide (${tz})" >&2
        exit 1
    fi
    if [[ ! -f "/usr/share/zoneinfo/${tz}" ]]; then
        echo "ERREUR: fuseau inconnu (${tz})" >&2
        exit 1
    fi
}

print_status() {
    local tz datetime ntp
    if command -v timedatectl &>/dev/null; then
        tz="$(timedatectl show -p Timezone --value 2>/dev/null || true)"
        ntp="$(timedatectl show -p NTP --value 2>/dev/null || echo unknown)"
    else
        tz="$(readlink -f /etc/localtime 2>/dev/null | sed 's|.*/zoneinfo/||' || true)"
        ntp="unknown"
    fi
    datetime="$(date -Iseconds 2>/dev/null || date '+%Y-%m-%d %H:%M:%S %Z')"
    echo "OBIORA_TZ_STATUS:timezone=${tz}"
    echo "OBIORA_TZ_STATUS:datetime=${datetime}"
    echo "OBIORA_TZ_STATUS:ntp=${ntp}"
}

case "${ACTION}" in
    status)
        print_status
        ;;
    set)
        require_root
        validate_tz "${TZ_NAME}"
        if command -v timedatectl &>/dev/null; then
            timedatectl set-timezone "${TZ_NAME}"
            timedatectl set-ntp true 2>/dev/null || true
        else
            ln -sf "/usr/share/zoneinfo/${TZ_NAME}" /etc/localtime
            if [[ -f /etc/sysconfig/clock ]]; then
                sed -i "s|^ZONE=.*|ZONE=${TZ_NAME}|" /etc/sysconfig/clock 2>/dev/null || true
            fi
        fi
        print_status
        echo "OBIORA_TZ_APPLIED:${TZ_NAME}"
        ;;
    *)
        echo "Usage: server-timezone.sh {status|set} [Timezone/Name]" >&2
        exit 1
        ;;
esac
