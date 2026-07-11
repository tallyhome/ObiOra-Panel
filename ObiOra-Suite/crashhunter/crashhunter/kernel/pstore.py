"""Linux persistent store (pstore / ramoops / EFI) — survives some panics across reboot."""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.kernel.pstore")

PSTORE_ROOT = Path("/sys/fs/pstore")


def read_pstore_at_boot() -> dict[str, Any]:
    """Aspirate all pstore entries immediately after boot (before other analysis)."""
    result: dict[str, Any] = {
        "available": PSTORE_ROOT.is_dir(),
        "backend": _read_backend(),
        "entries": [],
        "total_bytes": 0,
    }

    if not result["available"]:
        return result

    for path in sorted(PSTORE_ROOT.iterdir()):
        if not path.is_file():
            continue
        try:
            raw = path.read_bytes()
        except OSError as exc:
            result["entries"].append({"name": path.name, "error": str(exc)})
            continue

        entry: dict[str, Any] = {
            "name": path.name,
            "size": len(raw),
            "mtime": path.stat().st_mtime,
        }
        try:
            text = raw.decode("utf-8", errors="replace")
            entry["text"] = text[:200_000]
            entry["preview"] = text[:2000]
        except Exception:
            entry["hex_preview"] = raw[:512].hex()

        result["entries"].append(entry)
        result["total_bytes"] += len(raw)

    logger.info(
        "Pstore boot read: %d entries, %d bytes",
        len(result["entries"]),
        result["total_bytes"],
    )
    return result


def _read_backend() -> dict[str, str]:
    info: dict[str, str] = {}
    for name in ("backend", "compress", "pstore/firmware"):
        path = Path(f"/sys/module/pstore/parameters/{name.split('/')[-1]}")
        alt = Path(f"/sys/fs/pstore/{name}")
        for p in (path, alt):
            try:
                if p.exists():
                    info[name.replace("/", "_")] = p.read_text(encoding="utf-8", errors="replace").strip()
                    break
            except OSError:
                continue
    return info
