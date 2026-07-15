#!/usr/bin/env bash
# Profil Proxmox VE — détection + udev KVM si nécessaire

profile_precheck() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
    install_substep "Hôte Proxmox VE détecté"

    if [[ -e /dev/kvm ]] && declare -F ensure_kvm_udev_rules &>/dev/null; then
        ensure_kvm_udev_rules
    fi
}

profile_configure_ssh() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
    install_substep "SSH Proxmox : port ${OBIORA_SSH_PORT} (inchangé par défaut)"
}

profile_prompt_reboot() {
    if [[ ! -t 0 ]]; then
        return 0
    fi

    cat <<'MSG'

  ┌────────────────────────────────────────────────────────────┐
  │  Proxmox VE — vérifiez les règles udev KVM si appliquées   │
  │  Un reboot peut être nécessaire selon votre configuration. │
  └────────────────────────────────────────────────────────────┘

MSG
}
