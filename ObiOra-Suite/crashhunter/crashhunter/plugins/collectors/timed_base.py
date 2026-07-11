"""Timed collector base — measures command latency and raises alerts."""

from __future__ import annotations

import time
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.plugins.base import BaseCollector
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner


class TimedCollector(BaseCollector):
    """Base plugin that times every command and flags slow responses."""

    enabled: bool = True
    priority: int = 100
    failure_count: int = 0

    def __init__(self, settings: Settings, runner: SubprocessRunner) -> None:
        self.settings = settings
        self.runner = runner
        self._alerts: list[dict[str, Any]] = []

    def timed_command(
        self,
        key: str,
        command: list[str],
        timeout: float | None = None,
        max_output: int = 50000,
    ) -> dict[str, Any]:
        timeout = timeout if timeout is not None else self.settings.subprocess_timeout
        threshold_ms = self.settings.command_thresholds.get(key, self.settings.default_command_threshold_ms)
        start = time.monotonic()
        result = self.runner.run(command, timeout=timeout)
        latency_ms = round((time.monotonic() - start) * 1000, 2)
        slow = latency_ms > threshold_ms or result.timed_out
        entry = {
            "command": command,
            "latency_ms": latency_ms,
            "threshold_ms": threshold_ms,
            "timed_out": result.timed_out,
            "returncode": result.returncode,
            "slow": slow,
            "stdout": result.stdout[:max_output],
            "stderr": result.stderr[:5000],
        }
        if slow:
            self._alerts.append({"plugin": self.name, "command": key, "latency_ms": latency_ms, "timed_out": result.timed_out})
        return entry

    def read_proc(self, path: str, max_lines: int = 0) -> str:
        lines = ProcReader.read_lines(path)
        if max_lines > 0:
            lines = lines[:max_lines]
        return "\n".join(lines)

    def collect_meta(self) -> dict[str, Any]:
        return {"alerts": list(self._alerts), "failure_count": self.failure_count}

    def reset_alerts(self) -> None:
        self._alerts.clear()
