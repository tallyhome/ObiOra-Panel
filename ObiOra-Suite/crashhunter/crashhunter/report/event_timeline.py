"""Microsecond-precision event timeline."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

from crashhunter.utils.timestamp import now_us

logger = logging.getLogger("crashhunter.timeline")


class EventTimeline:
    """
    Chronological event log with microsecond precision.
    Persisted as JSONL for crash survival and report generation.
    """

    def __init__(self, timeline_file: Path, max_events: int = 10000) -> None:
        self.timeline_file = timeline_file
        self.max_events = max_events
        self._events: list[dict[str, Any]] = []
        self._load()

    def record(
        self,
        event: str,
        detail: str,
        severity: str = "info",
        extra: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        entry = {
            "timestamp": now_us(),
            "event": event,
            "detail": detail,
            "severity": severity,
        }
        if extra:
            entry.update(extra)
        self._events.append(entry)
        if len(self._events) > self.max_events:
            self._events = self._events[-self.max_events:]
        self._append_file(entry)
        return entry

    def record_snapshot_observation(self, snapshot: dict[str, Any]) -> None:
        """Record notable observations from a normal-mode snapshot."""
        cpu = snapshot.get("cpu", {}).get("total_percent", 0)
        if cpu < 80:
            return
        self.record("cpu_elevated", f"CPU at {cpu}%", severity="medium")

    def get_events(self) -> list[dict[str, Any]]:
        return list(self._events)

    def get_chronological_narrative(self) -> list[str]:
        """Human-readable chronological sequence."""
        lines: list[str] = []
        for entry in self._events:
            lines.append(f"{entry['timestamp']}  {entry['detail']}")
        return lines

    def clear(self) -> None:
        self._events.clear()

    def _append_file(self, entry: dict[str, Any]) -> None:
        try:
            self.timeline_file.parent.mkdir(parents=True, exist_ok=True)
            with self.timeline_file.open("a", encoding="utf-8") as fh:
                fh.write(json.dumps(entry, ensure_ascii=False) + "\n")
        except OSError as exc:
            logger.warning("Timeline persist failed: %s", exc)

    def _load(self) -> None:
        if not self.timeline_file.exists():
            return
        try:
            for line in self.timeline_file.read_text(encoding="utf-8").splitlines():
                if line.strip():
                    self._events.append(json.loads(line))
            if len(self._events) > self.max_events:
                self._events = self._events[-self.max_events:]
        except (OSError, json.JSONDecodeError) as exc:
            logger.warning("Timeline load failed: %s", exc)
