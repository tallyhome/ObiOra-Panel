#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if [[ -f /etc/webmin/miniserv.conf ]]; then
    systemctl enable --now webmin 2>/dev/null || systemctl restart webmin 2>/dev/null || true
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

systemctl enable --now webmin 2>/dev/null || systemctl restart webmin 2>/dev/null || true

version="$(webmin --version 2>/dev/null | awk '{print $2}' || true)"
[[ -z "${version}" && -f /usr/share/webmin/version ]] && version="$(tr -d ' \n\r' < /usr/share/webmin/version)"

echo "OK:webmin${version:+ version ${version}} (port 10000)"