"""Persistent storage for incident mode emergency snapshots."""

from __future__ import annotations

import json
import logging
import os
import tempfile
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.incident_store")


class IncidentStore:
    """Store high-frequency emergency snapshots per incident."""

    def __init__(self, incident_dir: Path) -> None:
        self.incident_dir = incident_dir
        self.incident_dir.mkdir(parents=True, exist_ok=True)
        self._counters: dict[str, int] = {}
        self._active_incidents: set[str] = set()
        self._restore_counters()

    def mark_active(self, incident_id: str) -> None:
        self._active_incidents.add(incident_id)

    def mark_inactive(self, incident_id: str) -> None:
        self._active_incidents.discard(incident_id)

    def is_active(self, incident_id: str) -> bool:
        return incident_id in self._active_incidents

    def append(self, incident_id: str, snapshot: dict[str, Any]) -> Path:
        incident_path = self._ensure_incident_dir(incident_id)
        idx = self._counters.get(incident_id, 0)
        path = incident_path / f"emergency_{idx:04d}.json"
        try:
            self._atomic_write_json(path, snapshot, indent=None)
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
        incident_path = self._ensure_incident_dir(incident_id)
        path = incident_path / "summary.json"
        try:
            self._atomic_write_json(path, summary, indent=2)
        except OSError as exc:
            logger.error("INCIDENT_SUMMARY_ATOMIC_WRITE_FAILED incident_id=%s error=%s", incident_id, exc)
            raise

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
        if not self.incident_dir.exists():
            return []
        return sorted(
            d.name for d in self.incident_dir.iterdir()
            if d.is_dir() and not d.name.startswith(".")
        )

    def load_summary(self, incident_id: str) -> dict[str, Any] | None:
        path = self.incident_dir / incident_id / "summary.json"
        if not path.exists():
            return None
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
            return data if isinstance(data, dict) else None
        except (OSError, json.JSONDecodeError):
            return None

    def _ensure_incident_dir(self, incident_id: str) -> Path:
        incident_path = self.incident_dir / incident_id
        incident_path.mkdir(parents=True, exist_ok=True)
        return incident_path

    def _restore_counters(self) -> None:
        for incident_id in self.list_incidents():
            count = len(list((self.incident_dir / incident_id).glob("emergency_*.json")))
            self._counters[incident_id] = count

    @staticmethod
    def _atomic_write_json(path: Path, payload: dict[str, Any], indent: int | None) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        text = json.dumps(payload, ensure_ascii=False, indent=indent)
        fd, tmp_name = tempfile.mkstemp(prefix=f".{path.name}.", dir=str(path.parent))
        tmp_path = Path(tmp_name)
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as fh:
                fh.write(text)
                fh.flush()
                os.fsync(fh.fileno())
            os.replace(tmp_path, path)
        finally:
            if tmp_path.exists():
                tmp_path.unlink(missing_ok=True)
