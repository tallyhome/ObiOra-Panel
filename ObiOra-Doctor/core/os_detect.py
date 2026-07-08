"""Linux distribution detection."""

from __future__ import annotations

from pathlib import Path
from typing import Any


SUPPORTED_DISTROS = {
    "almalinux",
    "rocky",
    "ubuntu",
    "debian",
    "centos",
    "rhel",
    "fedora",
}


def detect_os() -> dict[str, Any]:
    """Detect Linux distribution from /etc/os-release.

    Returns:
        Dictionary with id, name, version and family fields.

    Example:
        info = detect_os()
        info["id"]  # ubuntu
    """

    release = _parse_os_release()
    os_id = release.get("ID", "unknown").lower()
    id_like = release.get("ID_LIKE", "").lower()
    family = _resolve_family(os_id, id_like)

    return {
        "id": os_id,
        "name": release.get("PRETTY_NAME", release.get("NAME", "unknown")),
        "version": release.get("VERSION_ID", "unknown"),
        "family": family,
        "supported": family in SUPPORTED_DISTROS or os_id in SUPPORTED_DISTROS,
    }


def _parse_os_release() -> dict[str, str]:
    """Parse /etc/os-release into a dictionary."""

    path = Path("/etc/os-release")
    if not path.exists():
        return {}

    values: dict[str, str] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        if "=" not in line or line.startswith("#"):
            continue
        key, _, raw = line.partition("=")
        values[key] = raw.strip().strip('"')
    return values


def _resolve_family(os_id: str, id_like: str) -> str:
    """Resolve distribution family from ID and ID_LIKE."""

    if os_id in SUPPORTED_DISTROS:
        return os_id
    for token in id_like.split():
        if token in SUPPORTED_DISTROS:
            return token
    if "rhel" in id_like or "centos" in id_like:
        return "rhel"
    return os_id or "unknown"
