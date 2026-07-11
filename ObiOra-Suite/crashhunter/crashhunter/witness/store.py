"""Persistent witness heartbeat store on the VPS."""

from __future__ import annotations

import json
import logging
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.witness.store")


class WitnessStore:
    """Stores heartbeats and death events per monitored host."""

    def __init__(self, data_dir: Path) -> None:
        self.data_dir = data_dir
        self.data_dir.mkdir(parents=True, exist_ok=True)
        self.heartbeats_dir = self.data_dir / "heartbeats"
        self.heartbeats_dir.mkdir(parents=True, exist_ok=True)
        self.events_file = self.data_dir / "witness_events.jsonl"

    def record_heartbeat(self, payload: dict[str, Any]) -> None:
        host = str(payload.get("host", "unknown"))
        host_dir = self.heartbeats_dir / host
        host_dir.mkdir(parents=True, exist_ok=True)
        ts = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S_%f")
        path = host_dir / f"{ts}.json"
        path.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
        latest = host_dir / "latest.json"
        latest.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
        self._trim_history(host_dir, keep=120)

    def latest_heartbeat(self, host: str) -> dict[str, Any] | None:
        path = self.heartbeats_dir / host / "latest.json"
        if not path.exists():
            return None
        try:
            return json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return None

    def latest_sequence(self, host: str) -> int | None:
        latest = self.latest_heartbeat(host)
        if not latest:
            return None
        seq = latest.get("sequence_id")
        try:
            return int(seq) if seq is not None else None
        except (TypeError, ValueError):
            return None

    def list_hosts(self) -> list[str]:
        if not self.heartbeats_dir.exists():
            return []
        return sorted(
            p.name for p in self.heartbeats_dir.iterdir()
            if p.is_dir() and (p / "latest.json").exists()
        )

    def record_event(self, event: dict[str, Any]) -> None:
        event["recorded_at"] = datetime.now(timezone.utc).isoformat()
        try:
            with self.events_file.open("a", encoding="utf-8") as fh:
                fh.write(json.dumps(event, ensure_ascii=False) + "\n")
        except OSError as exc:
            logger.warning("Witness event write failed: %s", exc)

    def get_events(self, host: str | None = None, limit: int = 50) -> list[dict[str, Any]]:
        if not self.events_file.exists():
            return []
        events: list[dict[str, Any]] = []
        try:
            for line in self.events_file.read_text(encoding="utf-8").splitlines():
                if not line.strip():
                    continue
                evt = json.loads(line)
                if host and evt.get("host") != host:
                    continue
                events.append(evt)
        except (OSError, json.JSONDecodeError):
            return []
        return events[-limit:]

    @staticmethod
    def _trim_history(host_dir: Path, keep: int = 120) -> None:
        files = sorted(host_dir.glob("2*.json"), key=lambda p: p.name)
        for path in files[:-keep]:
            if path.name == "latest.json":
                continue
            path.unlink(missing_ok=True)
