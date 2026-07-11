"""Simulation mode — replay previous crash folders without reboot."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.report.blackbox import BlackBoxRecorder
from crashhunter.report.generator import ReportGenerator
from crashhunter.storage.incident_store import IncidentStore
from crashhunter.storage.ring_buffer import RingBuffer

logger = logging.getLogger("crashhunter.simulation")


class SimulationReplayer:
    """Replay incident/crash folders and regenerate analysis reports."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings

    def replay_incident_folder(self, folder: Path) -> dict[str, Any]:
        """Load emergency snapshots from an incident folder and generate report."""
        incident_store = IncidentStore(self.settings.incident_dir)
        incident_id = folder.name
        snapshots = incident_store.load_incident(incident_id)
        if not snapshots and folder.exists():
            snapshots = self._load_snapshots_from_folder(folder)

        ring = RingBuffer(self.settings.ring_capacity, self.settings.ring_dir)
        blackbox = BlackBoxRecorder(ring, self.settings.blackbox_memory_file)
        for snap in snapshots:
            blackbox.record(snap)

        reboot_info = {
            "reboot_detected": True,
            "reason": "simulation_replay",
            "simulated": True,
            "source_folder": str(folder),
            "snapshot_count": len(snapshots),
        }
        return ReportGenerator(self.settings).generate(
            blackbox, reboot_info, incident_id=incident_id,
        )

    def replay_report_folder(self, folder: Path) -> dict[str, Any]:
        """Regenerate report from an existing JSON report."""
        json_files = list(folder.glob("CrashReport_*.json"))
        if not json_files:
            raise FileNotFoundError(f"No CrashReport JSON in {folder}")
        report = json.loads(json_files[0].read_text(encoding="utf-8"))
        ring = RingBuffer(self.settings.ring_capacity, self.settings.ring_dir)
        blackbox = BlackBoxRecorder(ring, self.settings.blackbox_memory_file)
        for snap in report.get("blackbox", {}).get("last_snapshots", []):
            blackbox.record(snap)
        reboot_info = report.get("reboot_detection", {"reboot_detected": True, "reason": "simulation"})
        reboot_info["simulated"] = True
        return ReportGenerator(self.settings).generate(blackbox, reboot_info)

    @staticmethod
    def _load_snapshots_from_folder(folder: Path) -> list[dict[str, Any]]:
        snapshots: list[dict[str, Any]] = []
        for path in sorted(folder.glob("emergency_*.json")):
            try:
                snapshots.append(json.loads(path.read_text(encoding="utf-8")))
            except (OSError, json.JSONDecodeError):
                continue
        for path in sorted(folder.glob("snap_*.json")):
            try:
                snapshots.append(json.loads(path.read_text(encoding="utf-8")))
            except (OSError, json.JSONDecodeError):
                continue
        return snapshots

    def replay_timeline_step_by_step(self, folder: Path) -> list[str]:
        """Replay event timeline second by second for algorithm verification."""
        json_files = list(folder.glob("CrashReport_*.json"))
        if json_files:
            report = json.loads(json_files[0].read_text(encoding="utf-8"))
            events = report.get("event_timeline", [])
        else:
            snapshots = self._load_snapshots_from_folder(folder)
            events = [
                {"timestamp": s.get("timestamp_us", "?"), "detail": f"Snapshot mode={s.get('mode')}"}
                for s in snapshots
            ]
        lines: list[str] = []
        prev_ts = ""
        for event in events:
            ts = event.get("timestamp", "")
            if prev_ts and ts != prev_ts:
                lines.append(f"--- {ts} ---")
            lines.append(f"{ts}  [{event.get('severity', 'info')}] {event.get('detail', event.get('event', ''))}")
            prev_ts = ts
        return lines
