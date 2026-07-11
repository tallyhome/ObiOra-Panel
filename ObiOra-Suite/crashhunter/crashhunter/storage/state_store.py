"""Persistent state for boot detection and freeze tracking."""

from __future__ import annotations

import json
import logging
from datetime import datetime, timezone
from pathlib import Path

from crashhunter.utils.proc import ProcReader

logger = logging.getLogger("crashhunter.state")


class StateStore:
    """Tracks boot_id, uptime and clock across daemon restarts."""

    def __init__(
        self,
        boot_id_file: Path,
        last_uptime_file: Path,
        last_clock_file: Path,
    ) -> None:
        self.boot_id_file = boot_id_file
        self.last_uptime_file = last_uptime_file
        self.last_clock_file = last_clock_file
        for path in (boot_id_file, last_uptime_file, last_clock_file):
            path.parent.mkdir(parents=True, exist_ok=True)

    def current_boot_id(self) -> str:
        return ProcReader.boot_id()

    def current_uptime(self) -> float:
        return ProcReader.uptime()[0]

    def current_clock(self) -> str:
        return datetime.now(timezone.utc).isoformat()

    def load_previous_boot_id(self) -> str | None:
        if not self.boot_id_file.exists():
            return None
        return self.boot_id_file.read_text(encoding="utf-8").strip() or None

    def load_previous_uptime(self) -> float | None:
        if not self.last_uptime_file.exists():
            return None
        try:
            return float(self.last_uptime_file.read_text(encoding="utf-8").strip())
        except ValueError:
            return None

    def save_current_state(self) -> None:
        self.boot_id_file.write_text(self.current_boot_id(), encoding="utf-8")
        self.last_uptime_file.write_text(str(self.current_uptime()), encoding="utf-8")
        self.last_clock_file.write_text(self.current_clock(), encoding="utf-8")

    def detect_reboot(self) -> dict[str, object]:
        """Compare boot_id, uptime and clock to detect unexpected reboot."""
        prev_boot = self.load_previous_boot_id()
        curr_boot = self.current_boot_id()
        prev_uptime = self.load_previous_uptime()
        curr_uptime = self.current_uptime()

        reboot_detected = False
        reason = ""

        if prev_boot is not None and prev_boot != curr_boot:
            reboot_detected = True
            reason = "boot_id_changed"
        elif prev_uptime is not None and curr_uptime < prev_uptime:
            reboot_detected = True
            reason = "uptime_regression"

        result = {
            "reboot_detected": reboot_detected,
            "reason": reason,
            "previous_boot_id": prev_boot,
            "current_boot_id": curr_boot,
            "previous_uptime": prev_uptime,
            "current_uptime": curr_uptime,
            "detected_at": self.current_clock(),
        }
        logger.info("Boot check: %s", json.dumps(result))
        return result
