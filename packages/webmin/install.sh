#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

open_webmin_port() {
    if command -v firewall-cmd &>/dev/null && systemctl is-active --quiet firewalld 2>/dev/null; then
        firewall-cmd --permanent --add-port=10000/tcp >/dev/null 2>&1 || true
        firewall-cmd --reload >/dev/null 2>&1 || true
    fi

    if command -v ufw &>/dev/null && ufw status 2>/dev/null | grep -qiE 'Status: active|État : actif'; then
        ufw allow 10000/tcp >/dev/null 2>&1 || true
    fi

    if command -v semanage &>/dev/null; then
        semanage port -a -t http_port_t -p tcp 10000 >/dev/null 2>&1 \
            || semanage port -m -t http_port_t -p tcp 10000 >/dev/null 2>&1 \
            || true
    fi
}

ensure_webmin_listening() {
    local conf="/etc/webmin/miniserv.conf"
    if [[ ! -f "${conf}" ]]; then
        return 0
    fi

    # Certaines installs restreignent l'écoute à localhost — on force le port public.
    if grep -qE '^listen=127\.0\.0\.1' "${conf}"; then
        sed -i 's/^listen=127\.0\.0\.1/listen=10000/' "${conf}"
    elif ! grep -qE '^listen=' "${conf}"; then
        echo "listen=10000" >> "${conf}"
    fi
}

verify_webmin_service() {
    systemctl enable webmin 2>/dev/null || true
    systemctl restart webmin 2>/dev/null || systemctl start webmin 2>/dev/null || true
    sleep 2

    if ! systemctl is-active --quiet webmin; then
        echo "ERREUR: service webmin inactif après installation" >&2
        journalctl -u webmin -n 15 --no-pager >&2 || true
        exit 1
    fi

    if command -v ss &>/dev/null && ! ss -tln 2>/dev/null | grep -q ':10000'; then
        echo "ERREUR: Webmin n'écoute pas sur le port 10000" >&2
        journalctl -u webmin -n 15 --no-pager >&2 || true
        exit 1
    fi
}

if [[ -f /etc/webmin/miniserv.conf ]]; then
    ensure_webmin_listening
    open_webmin_port
    verify_webmin_service
    version="$(webmin --version 2>/dev/null | awk '{print $2}' || true)"
    [[ -z "${version}" && -f /usr/share/webmin/version ]] && version="$(tr -d ' \n\r' < /usr/share/webmin/version)"
    echo "OK:webmin (déjà installé${version:+ version ${version}}) (port 10000)"
    exit 0
fi

tmp_script="/tmp/webmin-setup-repo.sh"
curl -fsSL https://raw.githubusercontent.com/webmin/webmin/master/webmin-setup-repo.sh -o "${tmp_script}"
sh "${tmp_script}" --force
rm -f "${tmp_script}"

if command -v apt-get &>/dev/null; then
    DEBIAN_FRONTEND=noninteractive apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq webmin
elif command -v dnf &>/dev/null; then
    dnf install -y -q webmin
elif command -v yum &>/dev/null; then
    yum install -y -q webmin
else
    echo "ERREUR: gestionnaire de paquets non supporté (apt/dnf/yum)" >&2
    exit 1
fi

ensure_webmin_listening
open_webmin_port
verify_webmin_service

version="$(webmin --version 2>/dev/null | awk '{print $2}' || true)"
[[ -z "${version}" && -f /usr/share/webmin/version ]] && version="$(tr -d ' \n\r' < /usr/share/webmin/version)"

echo "OK:webmin${version:+ version ${version}} (port 10000)"
