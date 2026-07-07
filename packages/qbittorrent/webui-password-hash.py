#!/usr/bin/env python3
"""Génère le hash WebUI PBKDF2-SHA512 compatible qBittorrent (100k itérations)."""
from __future__ import annotations

import base64
import hashlib
import os
import sys


def hash_password(password: str) -> str:
    salt = os.urandom(16)
    digest = hashlib.pbkdf2_hmac('sha512', password.encode('utf-8'), salt, 100_000, dklen=64)
    return f"{base64.b64encode(salt).decode()}:{base64.b64encode(digest).decode()}"


if __name__ == '__main__':
    if len(sys.argv) != 2:
        print('usage: webui-password-hash.py <password>', file=sys.stderr)
        sys.exit(1)
    print(hash_password(sys.argv[1]))
