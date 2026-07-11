"""JSON report export."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any


def export_json(report: dict[str, Any], path: Path) -> Path:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    return path
