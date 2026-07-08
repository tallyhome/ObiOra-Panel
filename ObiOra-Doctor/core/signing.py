"""Cryptographic signing for Obiora Doctor reports."""

from __future__ import annotations

import hashlib
import hmac
import json
import secrets
from pathlib import Path
from typing import Any

SIGNING_KEY_FILE = Path(__file__).resolve().parents[1] / "config" / "signing.key"
PANEL_CONFIG_FILE = Path(__file__).resolve().parents[1] / "config" / "agent-panel.json"


def get_or_create_signing_key() -> bytes:
    """Load signing key from panel config, file, or generate a new one."""

    panel_key = _load_panel_signing_key()
    if panel_key:
        return panel_key

    if SIGNING_KEY_FILE.exists():
        return SIGNING_KEY_FILE.read_bytes().strip()
    key = secrets.token_bytes(32)
    SIGNING_KEY_FILE.parent.mkdir(parents=True, exist_ok=True)
    SIGNING_KEY_FILE.write_bytes(key + b"\n")
    SIGNING_KEY_FILE.chmod(0o600)
    return key


def export_signing_key_hex() -> str:
    """Return signing key as hex for panel .env sync."""

    return get_or_create_signing_key().hex()


def sign_report_dict(data: dict[str, Any]) -> dict[str, Any]:
    """Add HMAC-SHA256 signature to report dictionary."""

    key = get_or_create_signing_key()
    payload = json.dumps(data, sort_keys=True, ensure_ascii=False).encode("utf-8")
    signature = hmac.new(key, payload, hashlib.sha256).hexdigest()
    signed = dict(data)
    signed["signature"] = {"algorithm": "HMAC-SHA256", "value": signature}
    return signed


def verify_report_dict(data: dict[str, Any]) -> bool:
    """Verify report HMAC signature."""

    signature_block = data.get("signature")
    if not isinstance(signature_block, dict):
        return False
    expected = signature_block.get("value", "")
    unsigned = {k: v for k, v in data.items() if k != "signature"}
    key = get_or_create_signing_key()
    payload = json.dumps(unsigned, sort_keys=True, ensure_ascii=False).encode("utf-8")
    computed = hmac.new(key, payload, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, computed)


def _load_panel_signing_key() -> bytes | None:
    if not PANEL_CONFIG_FILE.exists():
        return None
    try:
        with PANEL_CONFIG_FILE.open(encoding="utf-8") as handle:
            config = json.load(handle)
    except (json.JSONDecodeError, OSError):
        return None
    raw = config.get("signing_key")
    if not raw:
        return None
    if isinstance(raw, str) and len(raw) == 64 and all(c in "0123456789abcdef" for c in raw.lower()):
        return bytes.fromhex(raw)
    if isinstance(raw, str):
        return raw.encode("utf-8")
    return None
