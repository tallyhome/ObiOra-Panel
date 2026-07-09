"""Collecte journalctl multi-boot (boot précédent après reboot)."""

from __future__ import annotations

import os
import re
from typing import Any

from crash_analyzer.util import run_cmd

MAX_PREV_BOOT_LINES = 300
MAX_PREV_BOOT_KERNEL_LINES = 150


def persistent_journal_enabled() -> bool:
    """Vérifie si le journal systemd est en mode persistent."""
    if os.path.isdir("/var/log/journal"):
        return True
    conf = "/etc/systemd/journald.conf"
    if not os.path.isfile(conf):
        return False
    try:
        with open(conf, encoding="utf-8", errors="replace") as fh:
            for line in fh:
                line = line.strip()
                if line.startswith("#") or "=" not in line:
                    continue
                key, val = line.split("=", 1)
                if key.strip() == "Storage":
                    return val.strip().lower() == "persistent"
    except OSError:
        pass
    return False


def parse_list_boots(raw: str) -> list[dict[str, Any]]:
    """Parse la sortie de journalctl --list-boots."""
    boots: list[dict[str, Any]] = []
    for line in raw.splitlines():
        line = line.strip()
        if not line or line.lower().startswith("idx"):
            continue
        # Format: -3 abc123... Mon 2026-01-01 12:00:00 UTC
        match = re.match(
            r"^(-?\d+)\s+(\S+)\s+(.+)$",
            line,
        )
        if match:
            boots.append({
                "index": int(match.group(1)),
                "id": match.group(2),
                "date": match.group(3).strip(),
            })
    return boots


def collect_boot_journal_snapshot() -> dict[str, Any]:
    """
    Collecte list-boots et le journal du boot précédent (-b -1).
    À appeler au démarrage après un reboot pour capturer la session crashée.
    """
    boots_raw = run_cmd(["journalctl", "--list-boots", "--no-pager"], timeout=6)
    boots = parse_list_boots(boots_raw)

    previous_boot_log = ""
    previous_boot_kernel = ""
    previous_boot_errors = ""
    previous_available = len(boots) >= 2

    if previous_available:
        previous_boot_log = run_cmd(
            [
                "journalctl", "-b", "-1", "--no-pager", "-o", "short-precise",
                "-n", str(MAX_PREV_BOOT_LINES),
            ],
            timeout=12,
        )
        previous_boot_kernel = run_cmd(
            [
                "journalctl", "-b", "-1", "-k", "--no-pager", "-o", "short-precise",
                "-n", str(MAX_PREV_BOOT_KERNEL_LINES),
            ],
            timeout=10,
        )
        previous_boot_errors = run_cmd(
            [
                "journalctl", "-b", "-1", "-p", "err..emerg", "--no-pager",
                "-o", "short-precise", "-n", "100",
            ],
            timeout=10,
        )

    return {
        "persistent_journal": persistent_journal_enabled(),
        "boots_raw": boots_raw[:4000],
        "boots": boots[-15:],
        "boots_count": len(boots),
        "previous_boot_available": previous_available,
        "previous_boot_log_tail": previous_boot_log[:12000],
        "previous_boot_kernel": previous_boot_kernel[:8000],
        "previous_boot_errors": previous_boot_errors[:8000],
    }
