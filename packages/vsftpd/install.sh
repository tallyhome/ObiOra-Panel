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

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq vsftpd
elif command -v dnf &>/dev/null; then
    dnf install -y vsftpd 2>/dev/null || { echo "Paquet vsftpd non disponible" >&2; exit 1; }
elif command -v yum &>/dev/null; then
    yum install -y vsftpd 2>/dev/null || { echo "Paquet vsftpd non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

mkdir -p "${ftp_root}/files"

if ! id "${ftp_user}" &>/dev/null; then
    useradd --home-dir "${ftp_root}" --create-home --shell /sbin/nologin "${ftp_user}" 2>/dev/null \
        || useradd --home-dir "${ftp_root}" --create-home --shell /usr/sbin/nologin "${ftp_user}"
fi

printf '%s:%s\n' "${ftp_user}" "${ftp_pass}" | chpasswd
chown root:root "${ftp_root}"
chmod 755 "${ftp_root}"
chown -R "${ftp_user}:${ftp_user}" "${ftp_root}/files"

cat > /etc/vsftpd/vsftpd.conf <<'EOF'
listen=YES
listen_ipv6=NO
anonymous_enable=NO
local_enable=YES
write_enable=YES
local_umask=022
dirmessage_enable=YES
xferlog_enable=YES
connect_from_port_20=YES
chroot_local_user=YES
allow_writeable_chroot=YES
local_root=/srv/ftp/$USER/files
user_sub_token=$USER
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
seccomp_sandbox=NO
pam_service_name=vsftpd
EOF

if command -v firewall-cmd &>/dev/null; then
    firewall-cmd --permanent --add-service=ftp >/dev/null 2>&1 || true
    firewall-cmd --permanent --add-port=40000-40100/tcp >/dev/null 2>&1 || true
    firewall-cmd --reload >/dev/null 2>&1 || true
fi

if command -v setsebool &>/dev/null; then
    setsebool -P ftpd_full_access on >/dev/null 2>&1 || true
fi

systemctl enable vsftpd 2>/dev/null || true
systemctl restart vsftpd 2>/dev/null || systemctl start vsftpd 2>/dev/null || true

echo "OK:vsftpd (port 21) credentials:${ftp_user}"