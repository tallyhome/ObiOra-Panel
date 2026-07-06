#!/usr/bin/env bash
# Vérification des prérequis système

check_prerequisites() {
    info "Vérification des prérequis..."

    # Architecture
    local arch
    arch="$(uname -m)"
    [[ "${arch}" == "x86_64" || "${arch}" == "aarch64" ]] || die "Architecture non supportée: ${arch}"

    # RAM minimum 1 Go
    local mem_kb
    mem_kb="$(grep MemTotal /proc/meminfo | awk '{print $2}')"
    if [[ "${mem_kb}" -lt 900000 ]]; then
        warn "RAM faible détectée (< 1 Go). Installation possible mais non recommandée."
    fi

    # Espace disque minimum 10 Go libre sur /
    local free_kb
    free_kb="$(df / | awk 'NR==2 {print $4}')"
    if [[ "${free_kb}" -lt 10485760 ]]; then
        die "Espace disque insuffisant. Minimum 10 Go requis sur /"
    fi

    # Ports critiques
    for port in 80 443; do
        if ss -tlnp 2>/dev/null | grep -q ":${port} "; then
            warn "Le port ${port} est déjà utilisé"
        fi
    done

    # Outils requis
    for cmd in curl wget git systemctl; do
        command -v "${cmd}" &>/dev/null || pkg_install "${cmd}"
    done

    success "Prérequis validés"
}
