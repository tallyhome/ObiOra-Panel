"""Utilitaires shell partagés."""

from __future__ import annotations

import subprocess


def run_cmd(cmd: list[str], timeout: float = 3.0) -> str:
    try:
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=timeout,
            check=False,
        )
        return (result.stdout or result.stderr or "").strip()
    except (subprocess.SubprocessError, OSError):
        return ""
