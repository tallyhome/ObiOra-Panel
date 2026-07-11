"""Persistent storage for incident mode emergency snapshots."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.incident_store")


class IncidentStore:
    """Store high-frequency emergency snapshots per incident."""

    def __init__(self, incident_dir: Path) -> None:
        self.incident_dir = incident_dir
        self.incident_dir.mkdir(parents=True, exist_ok=True)
        self._counters: dict[str, int] = {}

    def append(self, incident_id: str, snapshot: dict[str, Any]) -> Path:
        incident_path = self.incident_dir / incident_id
        incident_path.mkdir(parents=True, exist_ok=True)
        idx = self._counters.get(incident_id, 0)
        path = incident_path / f"emergency_{idx:04d}.json"
        try:
            path.write_text(json.dumps(snapshot, ensure_ascii=False), encoding="utf-8")
        except OSError as exc:
            logger.error("Failed to write emergency snapshot: %s", exc)
        self._counters[incident_id] = idx + 1
        return path

    def count(self, incident_id: str) -> int:
        incident_path = self.incident_dir / incident_id
        if not incident_path.exists():
            return self._counters.get(incident_id, 0)
        return len(list(incident_path.glob("emergency_*.json")))

    def save_summary(self, incident_id: str, summary: dict[str, Any]) -> None:
        path = self.incident_dir / incident_id / "summary.json"
        try:
            path.write_text(json.dumps(summary, indent=2), encoding="utf-8")
        except OSError as exc:
            logger.error("Failed to save incident summary: %s", exc)

    def load_incident(self, incident_id: str) -> list[dict[str, Any]]:
        incident_path = self.incident_dir / incident_id
        snapshots: list[dict[str, Any]] = []
        for path in sorted(incident_path.glob("emergency_*.json")):
            try:
                snapshots.append(json.loads(path.read_text(encoding="utf-8")))
            except (OSError, json.JSONDecodeError):
                continue
        return snapshots

    def list_incidents(self) -> list[str]:
        return sorted(
            d.name for d in self.incident_dir.iterdir()
            if d.is_dir() and not d.name.startswith(".")
        )
