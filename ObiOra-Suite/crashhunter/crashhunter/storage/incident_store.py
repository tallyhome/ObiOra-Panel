"""Persistent storage for incident mode emergency snapshots."""

from __future__ import annotations

import json
import logging
import os
import tempfile
import threading
from enum import Enum
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.incident_store")


class IncidentLifecycle(str, Enum):
    CREATING = "CREATING"
    ACTIVE = "ACTIVE"
    FINALIZING = "FINALIZING"
    CLOSED = "CLOSED"


class IncidentStore:
    """Store high-frequency emergency snapshots per incident."""

    def __init__(self, incident_dir: Path, retention_grace_seconds: float = 300.0) -> None:
        self.incident_dir = incident_dir
        self.incident_dir.mkdir(parents=True, exist_ok=True)
        self.retention_grace_seconds = retention_grace_seconds
        self._counters: dict[str, int] = {}
        self._active_incidents: set[str] = set()
        self._locks: dict[str, threading.RLock] = {}
        self._global_lock = threading.Lock()
        self._failed_capture: dict[str, int] = {}
        self._last_error: dict[str, str] = {}
        self._restore_counters()

    def ensure_incident_directory(self, incident_id: str) -> Path:
        """Atomically ensure incident directory exists."""
        with self._incident_lock(incident_id):
            incident_path = self.incident_dir / incident_id
            incident_path.mkdir(parents=True, exist_ok=True)
            if self.lifecycle(incident_id) is None:
                self._write_lifecycle(incident_id, IncidentLifecycle.CREATING)
                self._write_lifecycle(incident_id, IncidentLifecycle.ACTIVE)
            return incident_path

    def mark_active(self, incident_id: str) -> None:
        self._active_incidents.add(incident_id)
        self._write_lifecycle(incident_id, IncidentLifecycle.ACTIVE)

    def mark_finalizing(self, incident_id: str) -> None:
        self._write_lifecycle(incident_id, IncidentLifecycle.FINALIZING)

    def mark_inactive(self, incident_id: str) -> None:
        self._active_incidents.discard(incident_id)
        self._write_lifecycle(incident_id, IncidentLifecycle.CLOSED)

    def is_active(self, incident_id: str) -> bool:
        return incident_id in self._active_incidents

    def lifecycle(self, incident_id: str) -> IncidentLifecycle | None:
        path = self.incident_dir / incident_id / "lifecycle.json"
        if not path.exists():
            return None
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
            return IncidentLifecycle(str(data.get("status", "")))
        except (OSError, json.JSONDecodeError, ValueError):
            return None

    def can_cleanup(self, incident_id: str) -> bool:
        if self.is_active(incident_id):
            return False
        state = self.lifecycle(incident_id)
        if state in (IncidentLifecycle.ACTIVE, IncidentLifecycle.FINALIZING, IncidentLifecycle.CREATING):
            return False
        closed_path = self.incident_dir / incident_id / "lifecycle.json"
        if not closed_path.exists():
            return False
        try:
            data = json.loads(closed_path.read_text(encoding="utf-8"))
            closed_at = float(data.get("closed_at_mono", 0))
            if closed_at <= 0:
                return False
            import time
            return (time.monotonic() - closed_at) >= self.retention_grace_seconds
        except (OSError, json.JSONDecodeError, TypeError):
            return False

    def append(self, incident_id: str, snapshot: dict[str, Any]) -> Path:
        with self._incident_lock(incident_id):
            incident_path = self.ensure_incident_directory(incident_id)
            idx = self._counters.get(incident_id, 0)
            from crashhunter.utils.timestamp import now_iso_us
            ts = now_iso_us().replace(":", "").replace("-", "")
            path = incident_path / f"snapshot_{ts}_{idx:04d}.json"
            legacy_path = incident_path / f"emergency_{idx:04d}.json"
            try:
                self._atomic_write_json(path, snapshot, indent=None)
                if not legacy_path.exists():
                    try:
                        os.link(path, legacy_path)
                    except OSError:
                        self._atomic_write_json(legacy_path, snapshot, indent=None)
                self._counters[incident_id] = idx + 1
                return path
            except OSError as exc:
                self._failed_capture[incident_id] = self._failed_capture.get(incident_id, 0) + 1
                self._last_error[incident_id] = f"incident_directory_error: {exc}"
                logger.error("Failed to write emergency snapshot: %s", exc)
                return path

    def count(self, incident_id: str) -> int:
        incident_path = self.incident_dir / incident_id
        if not incident_path.exists():
            return self._counters.get(incident_id, 0)
        snapshot_files = list(incident_path.glob("snapshot_*.json"))
        if snapshot_files:
            return len(snapshot_files)
        return len(list(incident_path.glob("emergency_*.json")))

    def capture_stats(self, incident_id: str) -> dict[str, Any]:
        local = self.count(incident_id)
        failed = self._failed_capture.get(incident_id, 0)
        reason = self._last_error.get(incident_id)
        stats: dict[str, Any] = {
            "local_snapshots_count": local,
            "failed_snapshot_count": failed,
            "uploaded_snapshots_count": 0,
            "pending_upload_count": local,
        }
        if reason:
            stats["last_snapshot_error"] = reason
        if local == 0 and failed == 0:
            stats["capture_failure_reason"] = "process_stalled_before_snapshot"
        elif local == 0 and failed > 0:
            stats["capture_failure_reason"] = "incident_directory_error"
        return stats

    def save_summary(self, incident_id: str, summary: dict[str, Any]) -> None:
        with self._incident_lock(incident_id):
            self.mark_finalizing(incident_id)
            incident_path = self.ensure_incident_directory(incident_id)
            path = incident_path / "summary.json"
            summary = {
                **summary,
                "snapshot_capture": self.capture_stats(incident_id),
            }
            try:
                self._atomic_write_json(path, summary, indent=2)
            except OSError as exc:
                logger.error("INCIDENT_SUMMARY_ATOMIC_WRITE_FAILED incident_id=%s error=%s", incident_id, exc)
                raise
            finally:
                self.mark_inactive(incident_id)

    def load_incident(self, incident_id: str) -> list[dict[str, Any]]:
        incident_path = self.incident_dir / incident_id
        snapshots: list[dict[str, Any]] = []
        seen: set[str] = set()
        for pattern in ("emergency_*.json", "snapshot_*.json"):
            for path in sorted(incident_path.glob(pattern)):
                if path.name in seen or path.name == "summary.json":
                    continue
                seen.add(path.name)
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

    def _incident_lock(self, incident_id: str) -> threading.RLock:
        with self._global_lock:
            if incident_id not in self._locks:
                self._locks[incident_id] = threading.RLock()
            return self._locks[incident_id]

    def _write_lifecycle(self, incident_id: str, status: IncidentLifecycle) -> None:
        import time
        incident_path = self.incident_dir / incident_id
        incident_path.mkdir(parents=True, exist_ok=True)
        path = incident_path / "lifecycle.json"
        payload: dict[str, Any] = {"status": status.value, "updated_at": time.time()}
        if status == IncidentLifecycle.CLOSED:
            payload["closed_at_mono"] = time.monotonic()
        try:
            self._atomic_write_json(path, payload, indent=None)
        except OSError as exc:
            logger.warning("Lifecycle write failed for %s: %s", incident_id, exc)

    def _restore_counters(self) -> None:
        for incident_id in self.list_incidents():
            self._counters[incident_id] = self.count(incident_id)

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

    _ensure_incident_dir = ensure_incident_directory
