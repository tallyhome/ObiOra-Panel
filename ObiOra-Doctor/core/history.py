"""Report history and cleanup utilities."""

from __future__ import annotations

import json
import shutil
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any


def list_reports(reports_dir: Path) -> list[dict[str, Any]]:
    """List available reports sorted by date descending."""

    if not reports_dir.exists():
        return []

    entries: list[dict[str, Any]] = []
    for folder in reports_dir.iterdir():
        if not folder.is_dir():
            continue
        report_file = folder / "report.json"
        if not report_file.exists():
            continue
        with report_file.open(encoding="utf-8") as handle:
            data = json.load(handle)
        entries.append(
            {
                "path": str(folder),
                "date": data.get("generated_at", folder.name),
                "score": data.get("score", 0),
                "hostname": data.get("host", {}).get("hostname", "unknown"),
            }
        )
    entries.sort(key=lambda item: item["date"], reverse=True)
    return entries


def clean_old_reports(reports_dir: Path, retention_days: int) -> int:
    """Delete report folders older than retention_days.

    Returns:
        Number of deleted folders.
    """

    if not reports_dir.exists() or retention_days <= 0:
        return 0

    cutoff = datetime.now(timezone.utc) - timedelta(days=retention_days)
    deleted = 0
    for folder in reports_dir.iterdir():
        if not folder.is_dir():
            continue
        report_file = folder / "report.json"
        if not report_file.exists():
            continue
        with report_file.open(encoding="utf-8") as handle:
            data = json.load(handle)
        generated = data.get("generated_at", "")
        try:
            report_date = datetime.fromisoformat(generated.replace("Z", "+00:00"))
        except ValueError:
            continue
        if report_date < cutoff:
            shutil.rmtree(folder)
            deleted += 1
    return deleted
