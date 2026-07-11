"""Incident mode state machine and emergency sampling loop."""

from __future__ import annotations

import json
import logging
import time
from datetime import datetime, timezone
from enum import Enum
from pathlib import Path
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.freeze.detector import FreezeSignal
from crashhunter.freeze.emergency_collector import EmergencyCollector
from crashhunter.report.event_timeline import EventTimeline
from crashhunter.storage.incident_store import IncidentStore

logger = logging.getLogger("crashhunter.incident")


class DaemonMode(Enum):
    NORMAL = "normal"
    INCIDENT = "incident"


class IncidentManager:
    """
    Manages transition to emergency mode (500ms × 60s) on silent freeze detection.
    """

    def __init__(
        self,
        settings: Settings,
        emergency_collector: EmergencyCollector,
        incident_store: IncidentStore,
        timeline: EventTimeline,
    ) -> None:
        self.settings = settings
        self.emergency_collector = emergency_collector
        self.incident_store = incident_store
        self.timeline = timeline
        self.mode = DaemonMode.NORMAL
        self._incident_start: float | None = None
        self._incident_id: str | None = None
        self._triggers: list[str] = []

    @property
    def is_incident(self) -> bool:
        return self.mode == DaemonMode.INCIDENT

    def trigger(self, signals: list[FreezeSignal]) -> str:
        """Enter incident mode immediately."""
        self.mode = DaemonMode.INCIDENT
        self._incident_start = time.monotonic()
        self._triggers = [s.trigger for s in signals]
        self._incident_id = datetime.now().strftime("Incident_%Y%m%d_%H%M%S")
        trigger_detail = ", ".join(self._triggers)
        self.timeline.record(
            "incident_mode_started",
            f"Emergency mode activated: {trigger_detail}",
            severity="critical",
            extra={"incident_id": self._incident_id},
        )
        logger.critical("INCIDENT MODE triggered: %s", trigger_detail)
        self._persist_state()
        return self._incident_id

    def run_emergency_cycle(self) -> dict[str, Any] | None:
        """Run one emergency collection cycle. Returns None when incident ends."""
        if not self.is_incident or self._incident_start is None:
            return None

        elapsed = time.monotonic() - self._incident_start
        if elapsed >= self.settings.incident.emergency_duration_seconds:
            self._end_incident()
            return None

        snapshot = self.emergency_collector.collect_emergency_snapshot(self._triggers)
        self.incident_store.append(self._incident_id or "unknown", snapshot)
        return snapshot

    def emergency_interval(self) -> float:
        return self.settings.incident.emergency_interval_seconds

    def _end_incident(self) -> dict[str, Any]:
        self.timeline.record("incident_mode_ended", "Emergency collection complete", severity="info")
        summary = {
            "incident_id": self._incident_id,
            "triggers": self._triggers,
            "duration_seconds": self.settings.incident.emergency_duration_seconds,
            "snapshot_count": self.incident_store.count(self._incident_id or ""),
            "ended_at": datetime.now(timezone.utc).isoformat(),
        }
        self.incident_store.save_summary(self._incident_id or "unknown", summary)
        logger.info("Incident mode ended: %s", self._incident_id)
        self.mode = DaemonMode.NORMAL
        self._incident_start = None
        self._persist_state()
        return summary

    def _persist_state(self) -> None:
        state = {
            "mode": self.mode.value,
            "incident_id": self._incident_id,
            "triggers": self._triggers,
        }
        try:
            self.settings.incident_state_file.write_text(json.dumps(state), encoding="utf-8")
        except OSError as exc:
            logger.warning("Incident state persist failed: %s", exc)

    def load_state(self) -> None:
        path = self.settings.incident_state_file
        if not path.exists():
            return
        try:
            state = json.loads(path.read_text(encoding="utf-8"))
            if state.get("mode") == DaemonMode.INCIDENT.value:
                self.mode = DaemonMode.INCIDENT
                self._incident_id = state.get("incident_id")
                self._triggers = state.get("triggers", [])
                self._incident_start = time.monotonic()
        except (OSError, json.JSONDecodeError):
            pass
