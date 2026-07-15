#!/usr/bin/env bash
# ObiOra Panel — profils hôte dédié (générique : Virtualizor, Proxmox, bare metal…)

OBIORA_HOST_PROFILE="${OBIORA_HOST_PROFILE:-auto}"
OBIORA_ACTIVE_HOST_PROFILE="${OBIORA_ACTIVE_HOST_PROFILE:-}"
OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"

_dedicated_profile_dir() {
    echo "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/profiles"
}

_is_virtualizor_host() {
    [[ -d /usr/local/virtualizor ]] && return 0
    [[ -f /usr/local/virtualizor/version ]] && return 0
    systemctl list-unit-files 2>/dev/null | grep -q '^virtualizor\.service' && return 0
    return 1
}

_is_proxmox_host() {
    [[ -d /etc/pve ]] && return 0
    command -v pveversion &>/dev/null && return 0
    return 1
}

_is_solusvm_host() {
    [[ -d /usr/local/solusvm ]] && return 0
    [[ -d /etc/solusvm ]] && return 0
    return 1
}

detect_host_profile() {
    if [[ -n "${OBIORA_ACTIVE_HOST_PROFILE}" ]]; then
        return 0
    fi

    if [[ "${OBIORA_HOST_PROFILE}" != "auto" ]]; then
        OBIORA_ACTIVE_HOST_PROFILE="${OBIORA_HOST_PROFILE}"
        info "Profil hôte dédié forcé : ${OBIORA_ACTIVE_HOST_PROFILE}"
        return 0
    fi

    if _is_virtualizor_host; then
        OBIORA_ACTIVE_HOST_PROFILE="virtualizor"
    elif _is_proxmox_host; then
        OBIORA_ACTIVE_HOST_PROFILE="proxmox"
    elif _is_solusvm_host; then
        OBIORA_ACTIVE_HOST_PROFILE="solusvm"
    else
        OBIORA_ACTIVE_HOST_PROFILE="bare_metal"
    fi

    info "Profil hôte dédié détecté : ${OBIORA_ACTIVE_HOST_PROFILE}"
}

_load_profile_hooks() {
    local profile="${OBIORA_ACTIVE_HOST_PROFILE:-bare_metal}"
    local hook="${_dedicated_profile_dir}/${profile}.sh"

    if [[ -f "${hook}" ]]; then
        # shellcheck source=/dev/null
        source "${hook}"
    fi
}

setup_dedicated_precheck() {
    detect_host_profile
    _load_profile_hooks

    if declare -F profile_precheck &>/dev/null; then
        profile_precheck
    fi
}

setup_dedicated_post_ssh() {
    detect_host_profile
    _load_profile_hooks

    if declare -F profile_configure_ssh &>/dev/null; then
        profile_configure_ssh
    fi
}

prompt_dedicated_reboot() {
    detect_host_profile
    _load_profile_hooks

    if declare -F profile_prompt_reboot &>/dev/null; then
        profile_prompt_reboot
    fi
}

dedicated_summary_extra() {
    detect_host_profile

    case "${OBIORA_ACTIVE_HOST_PROFILE}" in
        virtualizor)
            cat <<EOF
  Profil   : Virtualizor (KVM)
  SSH      : port ${OBIORA_SSH_PORT:-2212} (ssh -p ${OBIORA_SSH_PORT:-2212} root@$(get_server_ip))
  KVM udev : ${OBIORA_KVM_UDEV_RULES:-/etc/udev/rules.d/65-kvm.rules}

EOF
            ;;
        proxmox)
            cat <<EOF
  Profil   : Proxmox VE
  SSH      : port ${OBIORA_SSH_PORT:-22}

EOF
            ;;
        solusvm)
            cat <<EOF
  Profil   : SolusVM
  SSH      : port ${OBIORA_SSH_PORT:-22}

EOF
            ;;
        bare_metal|custom)
            cat <<EOF
  Profil   : Dédié bare metal / générique
  SSH      : port ${OBIORA_SSH_PORT:-22}

EOF
            ;;
    esac
}
