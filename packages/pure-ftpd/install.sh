#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

ftp_user="${OBIORA_APP_USERNAME:-obioraftp}"
ftp_pass="${OBIORA_APP_PASS:-}"
ftp_root="/srv/ftp/${ftp_user}"

if [[ ! "${ftp_user}" =~ ^[a-z_][a-z0-9_-]{2,31}$ ]]; then
    echo "ERREUR: identifiant FTP invalide (lettres minuscules, chiffres, _ ou -, 3 à 32 caractères)." >&2
    exit 1
fi

if [[ -z "${ftp_pass}" ]]; then
    echo "ERREUR: mot de passe FTP requis." >&2
    exit 1
fi

if command -v dnf &>/dev/null; then
    dnf install -y epel-release >/dev/null 2>&1 || true
    dnf install -y pure-ftpd 2>/dev/null || { echo "Paquet pure-ftpd non disponible (EPEL requis)" >&2; exit 1; }
elif command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq pure-ftpd
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

mkdir -p "${ftp_root}/files"

if ! id "${ftp_user}" &>/dev/null; then
    useradd --home-dir "${ftp_root}" --create-home --shell /sbin/nologin "${ftp_user}" 2>/dev/null \
        || useradd --home-dir "${ftp_root}" --create-home --shell /usr/sbin/nologin "${ftp_user}"
fi

chown -R "${ftp_user}:${ftp_user}" "${ftp_root}"

if ! command -v pure-pw &>/dev/null; then
    echo "ERREUR: pure-pw introuvable après installation." >&2
    exit 1
fi

pure-pw userdel "${ftp_user}" 2>/dev/null || true
printf '%s\n%s\n' "${ftp_pass}" "${ftp_pass}" | pure-pw useradd "${ftp_user}" -u "${ftp_user}" -d "${ftp_root}/files" -m

mkdir -p /etc/pure-ftpd/conf
echo "yes" > /etc/pure-ftpd/conf/ChrootEveryone
echo "yes" > /etc/pure-ftpd/conf/CreateHomeDir
echo "yes" > /etc/pure-ftpd/conf/DontResolve
echo "40100 40200" > /etc/pure-ftpd/conf/PassivePortRange

if command -v firewall-cmd &>/dev/null; then
    firewall-cmd --permanent --add-service=ftp >/dev/null 2>&1 || true
    firewall-cmd --permanent --add-port=40100-40200/tcp >/dev/null 2>&1 || true
    firewall-cmd --reload >/dev/null 2>&1 || true
fi

systemctl enable pure-ftpd 2>/dev/null || systemctl enable pure-ftpd.service 2>/dev/null || true
systemctl restart pure-ftpd 2>/dev/null || systemctl restart pure-ftpd.service 2>/dev/null || true

echo "OK:pure-ftpd (port 21) credentials:${ftp_user}"
