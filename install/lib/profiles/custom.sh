#!/usr/bin/env bash
# Profil personnalisé — comportement identique au bare metal

profile_precheck() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
    install_substep "Profil personnalisé — configurez les hooks via OBIORA_HOST_PROFILE=custom"
}

profile_configure_ssh() {
    OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
}

profile_prompt_reboot() {
    return 0
}
