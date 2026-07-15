#!/usr/bin/env bash
# ObiOra Panel — dédié Virtualizor (KVM, SSH, reboot)

OBIORA_IS_VIRTUALIZOR="${OBIORA_IS_VIRTUALIZOR:-false}"
OBIORA_SSH_PORT="${OBIORA_SSH_PORT:-22}"
OBIORA_VIRTUALIZOR_SSH_PORT="${OBIORA_VIRTUALIZOR_SSH_PORT:-2212}"
OBIORA_KVM_UDEV_RULES="/etc/udev/rules.d/65-kvm.rules"
OBIORA_KVM_UDEV_EXPECTED='KERNEL=="kvm", GROUP="kvm", MODE="0660"'

is_virtualizor_host() {
    if [[ -d /usr/local/virtualizor ]]; then
        return 0
    fi
    if [[ -f /usr/local/virtualizor/version ]]; then
        return 0
    fi
    if systemctl list-unit-files 2>/dev/null | grep -q '^virtualizor\.service'; then
        return 0
    fi
    return 1
}

detect_virtualizor_host() {
    if is_virtualizor_host; then
        OBIORA_IS_VIRTUALIZOR="true"
        info "Hôte Virtualizor détecté — correctifs KVM / SSH ${OBIORA_VIRTUALIZOR_SSH_PORT} activés"
        return 0
    fi
    OBIORA_IS_VIRTUALIZOR="false"
    return 1
}

_kvm_udev_rules_ok() {
    [[ -f "${OBIORA_KVM_UDEV_RULES}" ]] || return 1
    grep -qF 'KERNEL=="kvm"' "${OBIORA_KVM_UDEV_RULES}" \
        && grep -qF 'GROUP="kvm"' "${OBIORA_KVM_UDEV_RULES}" \
        && grep -qF 'MODE="0660"' "${OBIORA_KVM_UDEV_RULES}"
}

ensure_kvm_udev_rules() {
    if _kvm_udev_rules_ok; then
        install_substep "Règles udev KVM déjà correctes (${OBIORA_KVM_UDEV_RULES})"
        return 0
    fi

    install_substep "Application des règles udev KVM (Virtualizor / libvirt)…"
    getent group kvm &>/dev/null || groupadd -r kvm 2>/dev/null || groupadd kvm 2>/dev/null || true

    cat > "${OBIORA_KVM_UDEV_RULES}" <<'EOF'
KERNEL=="kvm", GROUP="kvm", MODE="0660"
EOF
    chmod 644 "${OBIORA_KVM_UDEV_RULES}"

    if command -v udevadm &>/dev/null; then
        udevadm control --reload-rules >> "${OBIORA_LOG_FILE}" 2>&1
        if [[ -e /dev/kvm ]]; then
            udevadm trigger /dev/kvm >> "${OBIORA_LOG_FILE}" 2>&1 || true
        else
            udevadm trigger -c add -s char -p SUBSYSTEM=kvm >> "${OBIORA_LOG_FILE}" 2>&1 || true
        fi
    fi

    success "Règles udev KVM appliquées (${OBIORA_KVM_UDEV_RULES})"
}

_get_sshd_port() {
    local port=""
    port="$(sshd -T 2>/dev/null | awk '/^port / {print $2; exit}')" || true
    if [[ -n "${port}" ]]; then
        echo "${port}"
        return 0
    fi
    port="$(grep -E '^[[:space:]]*Port[[:space:]]+' /etc/ssh/sshd_config 2>/dev/null | awk '{print $2}' | tail -1)" || true
    echo "${port:-22}"
}

configure_virtualizor_ssh_port() {
    local target_port="${OBIORA_VIRTUALIZOR_SSH_PORT}"
    local current_port
    current_port="$(_get_sshd_port)"

    if [[ "${current_port}" == "${target_port}" ]]; then
        install_substep "SSH déjà sur le port ${target_port}"
        OBIORA_SSH_PORT="${target_port}"
        return 0
    fi

    install_substep "Configuration SSH port ${target_port} (actuel : ${current_port})…"

    if [[ -d /etc/ssh/sshd_config.d ]]; then
        cat > /etc/ssh/sshd_config.d/99-obiora-virtualizor.conf <<EOF
# ObiOra Panel — dédié Virtualizor
Port ${target_port}
EOF
        chmod 644 /etc/ssh/sshd_config.d/99-obiora-virtualizor.conf
    fi

    if [[ -f /etc/ssh/sshd_config ]]; then
        if grep -qE '^[[:space:]]*#?[[:space:]]*Port[[:space:]]+' /etc/ssh/sshd_config; then
            sed -i -E "s/^[[:space:]]*#?[[:space:]]*Port[[:space:]]+.*/Port ${target_port}/" /etc/ssh/sshd_config
        else
            printf '\nPort %s\n' "${target_port}" >> /etc/ssh/sshd_config
        fi
    fi

    if command -v getenforce &>/dev/null && [[ "$(getenforce)" != "Disabled" ]] && command -v semanage &>/dev/null; then
        semanage port -a -t ssh_port_t -p tcp "${target_port}" >> "${OBIORA_LOG_FILE}" 2>&1 \
            || semanage port -m -t ssh_port_t -p tcp "${target_port}" >> "${OBIORA_LOG_FILE}" 2>&1 \
            || true
    fi

    if ! sshd -t >> "${OBIORA_LOG_FILE}" 2>&1; then
        warn "Configuration SSH invalide — port ${target_port} non appliqué (voir ${OBIORA_LOG_FILE})"
        OBIORA_SSH_PORT="22"
        return 1
    fi

    systemctl restart sshd >> "${OBIORA_LOG_FILE}" 2>&1 \
        || systemctl restart ssh >> "${OBIORA_LOG_FILE}" 2>&1 \
        || die "Impossible de redémarrer le service SSH"

    OBIORA_SSH_PORT="${target_port}"
    success "SSH configuré sur le port ${target_port}"
    warn "Connexion future : ssh -p ${target_port} root@$(get_server_ip)"
}

setup_virtualizor_kvm() {
    detect_virtualizor_host || return 0
    ensure_kvm_udev_rules
}

setup_virtualizor_ssh() {
    detect_virtualizor_host || return 0
    configure_virtualizor_ssh_port
}

prompt_virtualizor_reboot() {
    if [[ "${OBIORA_IS_VIRTUALIZOR}" != "true" ]]; then
        return 0
    fi

    cat <<'MSG'

  ┌────────────────────────────────────────────────────────────┐
  │  Virtualizor — redémarrage recommandé                        │
  │  Les règles udev KVM et le port SSH 2212 sont plus sûrs     │
  │  après un reboot complet du serveur.                         │
  └────────────────────────────────────────────────────────────┘

MSG

    if [[ ! -t 0 ]]; then
        warn "Terminal non interactif — redémarrage non lancé automatiquement."
        warn "Exécutez « reboot » quand vous le pouvez."
        return 0
    fi

    local choice=""
    read -r -p "  Redémarrer le serveur maintenant ? [o/N] : " choice
    case "${choice}" in
        o|O|y|Y|oui|Oui|OUI)
            warn "Redémarrage dans 5 secondes… (Ctrl+C pour annuler)"
            sleep 5
            reboot
            ;;
        *)
            info "Redémarrage reporté — pensez à « reboot » après vérification."
            ;;
    esac
}
