#!/usr/bin/env bash
# Profil Virtualizor — délègue aux helpers historiques (lib/virtualizor.sh)

profile_precheck() {
    OBIORA_IS_VIRTUALIZOR="true"
    setup_virtualizor_kvm
}

profile_configure_ssh() {
    OBIORA_IS_VIRTUALIZOR="true"
    setup_virtualizor_ssh
}

profile_prompt_reboot() {
    prompt_virtualizor_reboot
}
