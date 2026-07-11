"""Magic SysRq — kernel diagnostics when userspace is frozen."""

from __future__ import annotations

import logging
import time
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.sysrq")

SYSRQ_TRIGGER = Path("/proc/sysrq-trigger")


class SysRqController:
    """Send Magic SysRq commands to the kernel."""

    def __init__(self, enabled: bool = True) -> None:
        self.enabled = enabled

    def is_available(self) -> bool:
        return SYSRQ_TRIGGER.exists() and SYSRQ_TRIGGER.is_file()

    def send(self, key: str) -> dict[str, Any]:
        """Send a single SysRq key (t, w, l, m, p, etc.)."""
        if not self.enabled:
            return {"key": key, "sent": False, "reason": "disabled"}
        if not self.is_available():
            return {"key": key, "sent": False, "reason": "sysrq_not_available"}
        try:
            SYSRQ_TRIGGER.write_text(key, encoding="ascii")
            logger.info("SysRq sent: %s", key)
            return {"key": key, "sent": True}
        except OSError as exc:
            logger.warning("SysRq %s failed: %s", key, exc)
            return {"key": key, "sent": False, "reason": str(exc)}

    def diagnostic_burst(self) -> list[dict[str, Any]]:
        """Send t (tasks), w (blocked), l (backtrace) — classic triage."""
        results = []
        for key in ("t", "w", "l"):
            results.append(self.send(key))
            time.sleep(0.5)
        return results


class SysRqSequence:
    """
    Magic SysRq watchdog sequence during incident mode:
    T → wait → W → wait → capture → L
    """

    def __init__(
        self,
        controller: SysRqController,
        wait_seconds: float = 2.0,
        trigger_after_seconds: float = 10.0,
    ) -> None:
        self.controller = controller
        self.wait_seconds = wait_seconds
        self.trigger_after_seconds = trigger_after_seconds

    def run(self, capture_callback: Any = None) -> dict[str, Any]:
        """Execute the full SysRq watchdog sequence."""
        log: list[dict[str, Any]] = []
        start = time.monotonic()

        log.append({"step": "sysrq_t", **self.controller.send("t")})
        time.sleep(self.wait_seconds)

        log.append({"step": "sysrq_w", **self.controller.send("w")})
        time.sleep(self.wait_seconds)

        capture_result: dict[str, Any] = {}
        if capture_callback:
            try:
                capture_result = capture_callback()
            except Exception as exc:
                capture_result = {"error": str(exc)}
        log.append({"step": "capture", "result": capture_result})

        log.append({"step": "sysrq_l", **self.controller.send("l")})

        return {
            "sequence": "T-W-capture-L",
            "duration_seconds": round(time.monotonic() - start, 2),
            "steps": log,
        }

    def should_trigger(self, incident_elapsed_seconds: float) -> bool:
        return incident_elapsed_seconds >= self.trigger_after_seconds
