#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

web_user="$(obiora_auth_user)"
web_pass="$(obiora_auth_pass)"
name="obiora-calibreweb"
image="lscr.io/linuxserver/calibre-web:latest"
host_port=8083
data_dir="/var/lib/obiora/calibreweb"

obiora_require_root
obiora_require_docker

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:calibreweb (déjà installé)"
    exit 0
fi

mkdir -p "${data_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"

obiora_docker_install "calibreweb" "${image}" "${host_port}"

for _ in $(seq 1 45); do
    if docker exec "${name}" test -f /config/app.db 2>/dev/null; then
        break
    fi
    sleep 2
done

if ! docker exec "${name}" test -f /config/app.db 2>/dev/null; then
    echo "ERREUR: base Calibre-Web (/config/app.db) introuvable après démarrage." >&2
    exit 1
fi

docker exec -e "CB_USER=${web_user}" -e "CB_PASS=${web_pass}" "${name}" python3 - <<'PY'
import os
import sqlite3

from werkzeug.security import generate_password_hash

user = os.environ["CB_USER"]
password = os.environ["CB_PASS"]
hashed = generate_password_hash(password)

conn = sqlite3.connect("/config/app.db")
cur = conn.cursor()
cur.execute("UPDATE user SET password = ? WHERE name = ?", (hashed, user))
if cur.rowcount == 0:
    cur.execute("UPDATE user SET password = ? WHERE name = 'admin'", (hashed,))
    if user != "admin":
        cur.execute("UPDATE user SET name = ? WHERE name = 'admin'", (user,))
conn.commit()
conn.close()
PY

echo "OK:calibreweb (port ${host_port}) credentials:${web_user}"
