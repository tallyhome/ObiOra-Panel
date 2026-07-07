#!/usr/bin/env bash
# Compile un binaire setuid root pour lancer update-panel.sh (worker obiora-queue).
# Le setuid sur un script bash est ignoré par le noyau Linux — seul un ELF fonctionne.

setup_panel_update_helper() {
    local helper="/usr/local/bin/obiora-panel-update"
    local src="${OBIORA_INSTALL_DIR}/install/lib/panel-update-helper.c"
    local update_script="${OBIORA_INSTALL_DIR}/install/update-panel.sh"
    local obiora_group="${OBIORA_GROUP:-obiora}"
    local install_dir="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"

    if [[ ! -f "${update_script}" ]]; then
        warn "update-panel.sh introuvable — helper MAJ non installé"
        return 0
    fi

    if [[ ! -f "${src}" ]]; then
        warn "panel-update-helper.c introuvable — sudo sera utilisé pour les MAJ"
        _remove_legacy_bash_helper "${helper}"
        return 0
    fi

    info "Compilation du helper de mise à jour panel (binaire setuid)..."

    if ! command -v gcc &>/dev/null; then
        if command -v dnf &>/dev/null; then
            dnf install -y -q gcc 2>/dev/null || true
        elif command -v apt-get &>/dev/null; then
            DEBIAN_FRONTEND=noninteractive apt-get install -y -qq gcc 2>/dev/null || true
        fi
    fi

    if ! command -v gcc &>/dev/null; then
        warn "gcc indisponible — helper setuid non compilé (sudo NOPASSWD sera utilisé)"
        _remove_legacy_bash_helper "${helper}"
        return 0
    fi

    _remove_legacy_bash_helper "${helper}"

    local tmp="${helper}.build.$$"
    if ! gcc -O2 -Wall -Wextra \
        -DOBIORA_INSTALL_DIR="\"${install_dir}\"" \
        -o "${tmp}" "${src}"; then
        warn "Échec compilation helper MAJ — sudo sera utilisé"
        rm -f "${tmp}"
        return 0
    fi

    install -o root -g "${obiora_group}" -m 4750 "${tmp}" "${helper}"
    rm -f "${tmp}"

    if ! _helper_is_elf "${helper}"; then
        warn "Helper MAJ invalide après installation"
        rm -f "${helper}"
        return 0
    fi

    success "Helper MAJ installé : ${helper} (binaire setuid)"
}

_remove_legacy_bash_helper() {
    local helper="$1"

    [[ -f "${helper}" ]] || return 0

    # Ancien helper bash (setuid ignoré par Linux) — à supprimer
    if head -c 2 "${helper}" 2>/dev/null | grep -q '^#!'; then
        warn "Suppression de l'ancien helper bash (setuid non fonctionnel)"
        rm -f "${helper}"
    fi
}

_helper_is_elf() {
    local file="$1"
    [[ -f "${file}" ]] || return 1
    [[ "$(head -c 4 "${file}" 2>/dev/null || true)" == $'\x7fELF' ]]
}
