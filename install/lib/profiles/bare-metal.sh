#!/usr/bin/env bash
# Profil bare metal — aucun correctif hyperviseur

profile_precheck() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
    install_substep "Profil bare metal — pas de correctif KVM / SSH spécifique"
}

profile_configure_ssh() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
}

profile_prompt_reboot() {
    return 0
}
