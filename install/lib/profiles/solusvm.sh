#!/usr/bin/env bash
# Profil SolusVM — détection légère

profile_precheck() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
    install_substep "Hôte SolusVM détecté — profil générique (hooks à venir)"
}

profile_configure_ssh() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
}

profile_prompt_reboot() {
    return 0
}
