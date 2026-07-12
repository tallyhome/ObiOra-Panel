"""Incident mode state machine and emergency sampling loop."""

from __future__ import annotations

import json
import logging
import threading
import time
from datetime import datetime, timezone
from enum import Enum
from pathlib import Path
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.freeze.detector import FreezeSignal
from crashhunter.freeze.emergency_collector import EmergencyCollector
from crashhunter.kernel.sysrq import SysRqController, SysRqSequence
from crashhunter.report.event_timeline import EventTimeline
from crashhunter.storage.incident_store import IncidentStore

logger = logging.getLogger("crashhunter.incident")


class DaemonMode(Enum):
    NORMAL = "normal"
    INCIDENT = "incident"


class IncidentManager:
    """
    Manages transition to emergency mode (500ms × 60s) on silent freeze detection.
    Triggers SysRq diagnostics and deep collectors during incident.
    """

    def __init__(
        self,
        settings: Settings,
        emergency_collector: EmergencyCollector,
        incident_store: IncidentStore,
        timeline: EventTimeline,
        sysrq: SysRqController | None = None,
    ) -> None:
        self.settings = settings
        self.emergency_collector = emergency_collector
        self.incident_store = incident_store
        self.timeline = timeline
        self.sysrq = sysrq or SysRqController(settings.sysrq.enabled)
        self.mode = DaemonMode.NORMAL
        self._incident_start: float | None = None
        self._incident_id: str | None = None
        self._triggers: list[str] = []
        self._sysrq_done = False
        self._deep_collectors_started = False
        self._started_at_iso: str | None = None

    @property
    def is_incident(self) -> bool:
        return self.mode == DaemonMode.INCIDENT

    @property
    def started_at_iso(self) -> str | None:
        return self._started_at_iso

    @property
    def incident_id(self) -> str | None:
        return self._incident_id

    def trigger(self, signals: list[FreezeSignal]) -> str:
        """Enter incident mode immediately."""
        self.mode = DaemonMode.INCIDENT
        self._incident_start = time.monotonic()
        self._triggers = [s.trigger for s in signals]
        self._incident_id = datetime.now().strftime("Incident_%Y%m%d_%H%M%S")
        self._started_at_iso = datetime.now(timezone.utc).isoformat()
        self._sysrq_done = False
        self._deep_collectors_started = False
        self.incident_store.ensure_incident_directory(self._incident_id)
        self.incident_store.mark_active(self._incident_id)
        trigger_detail = ", ".join(self._triggers)
        self.timeline.record(
            "incident_mode_started",
            f"Emergency mode activated: {trigger_detail}",
            severity="critical",
            extra={"incident_id": self._incident_id},
        )
        logger.critical("INCIDENT MODE triggered: %s", trigger_detail)

        if self.settings.sysrq.enabled and self.settings.sysrq.auto_on_incident:
            burst = self.sysrq.diagnostic_burst()
            self.timeline.record("sysrq_burst", f"SysRq t/w/l sent: {burst}", severity="warning")

        self._start_deep_collectors()
        self._persist_state()
        return self._incident_id

    def _start_deep_collectors(self) -> None:
        if self._deep_collectors_started:
            return
        if not self.emergency_collector.budget.allow_heavy_diagnostics():
            logger.warning("Deep collectors skipped — diagnostic budget in minimal mode")
            return
        self._deep_collectors_started = True
        threading.Thread(target=self.emergency_collector.run_deep_diagnostics, daemon=True).start()

    def run_emergency_cycle(self) -> dict[str, Any] | None:
        """Run one emergency collection cycle. Returns None when incident ends."""
        if not self.is_incident or self._incident_start is None:
            return None

        elapsed = time.monotonic() - self._incident_start
        if elapsed >= self.settings.incident.emergency_duration_seconds:
            self._end_incident()
            return None

        if (
            self.settings.sysrq.enabled
            and self.settings.sysrq.watchdog_sequence
            and not self._sysrq_done
        ):
            seq = SysRqSequence(
                self.sysrq,
                wait_seconds=self.settings.sysrq.sequence_wait_seconds,
                trigger_after_seconds=self.settings.sysrq.trigger_after_seconds,
            )
            if seq.should_trigger(elapsed):
                result = seq.run(capture_callback=self.emergency_collector.collect_quick_snapshot)
                self.timeline.record("sysrq_watchdog_sequence", "T-W-capture-L executed", severity="critical", extra=result)
                self._sysrq_done = True

        snapshot = self.emergency_collector.collect_emergency_snapshot(self._triggers)
        self.incident_store.append(self._incident_id or "unknown", snapshot)
        return snapshot

    def emergency_interval(self) -> float:
        return self.settings.incident.emergency_interval_seconds

    def _end_incident(self) -> dict[str, Any]:
        self.timeline.record("incident_mode_ended", "Emergency collection complete", severity="info")
        deep = self.emergency_collector.get_deep_results()
        summary = {
            "incident_id": self._incident_id,
            "triggers": self._triggers,
            "status": "ended",
            "started_at": self._started_at_iso,
            "duration_seconds": self.settings.incident.emergency_duration_seconds,
            "snapshot_count": self.incident_store.count(self._incident_id or ""),
            "ended_at": datetime.now(timezone.utc).isoformat(),
            "deep_diagnostics": deep,
        }
        self.incident_store.save_summary(self._incident_id or "unknown", summary)
        if self._incident_id:
            self.incident_store.mark_inactive(self._incident_id)
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
                if self._incident_id:
                    self.incident_store.ensure_incident_directory(self._incident_id)
                    self.incident_store.mark_active(self._incident_id)
        except (OSError, json.JSONDecodeError):
            pass
