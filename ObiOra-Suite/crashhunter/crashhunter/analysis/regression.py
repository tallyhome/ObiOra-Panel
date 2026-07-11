"""Regression detector — kernel/Virtualizor/config changes before crashes."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.regression")


class RegressionDetector:
    """Track version changes and flag if they precede crashes systematically."""

    def __init__(self, state_file: Path) -> None:
        self.state_file = state_file

    def check(self, version_signature: dict[str, Any]) -> dict[str, Any]:
        previous = self._load_previous()
        changes: list[dict[str, str]] = []
        for key in ("kernel", "virtualizor", "libvirt", "qemu", "bios_version"):
            old = previous.get(key, "")
            new = version_signature.get(key, "")
            if old and new and old != new:
                changes.append({"component": key, "before": old[:200], "after": new[:200]})

        regressions: list[dict[str, Any]] = []
        history = previous.get("change_history", [])
        if changes:
            history.append({"changes": changes, "signature": version_signature})
            history = history[-20:]

        crash_count = previous.get("crash_count", 0) + 1
        for change in changes:
            component = change["component"]
            same_component_crashes = sum(
                1 for h in history
                if any(c.get("component") == component for c in h.get("changes", []))
            )
            if same_component_crashes >= 2:
                regressions.append({
                    "component": component,
                    "message": f"Changement {component} précède {same_component_crashes} crash(s)",
                    "confidence": min(0.9, 0.5 + same_component_crashes * 0.15),
                    "before": change["before"],
                    "after": change["after"],
                })

        self._save({"last_signature": version_signature, "change_history": history, "crash_count": crash_count})
        return {"changes": changes, "regressions": regressions, "crash_count": crash_count}

    def _load_previous(self) -> dict[str, Any]:
        if not self.state_file.exists():
            return {}
        try:
            return json.loads(self.state_file.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return {}

    def _save(self, data: dict[str, Any]) -> None:
        try:
            self.state_file.parent.mkdir(parents=True, exist_ok=True)
            self.state_file.write_text(json.dumps(data, indent=2), encoding="utf-8")
        except OSError as exc:
            logger.warning("Regression state save failed: %s", exc)
