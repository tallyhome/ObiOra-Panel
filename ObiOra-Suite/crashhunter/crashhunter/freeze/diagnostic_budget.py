"""Observer-effect protection — reduce diagnostic load when host is near freeze."""

from __future__ import annotations

import logging
import time
from dataclasses import dataclass, field
from typing import Any

logger = logging.getLogger("crashhunter.freeze.budget")

HEAVY_TOOLS = frozenset({"perf", "ftrace", "qemu_gdb"})


@dataclass
class DiagnosticBudget:
    """Dynamic budget: disable heavy tools when PSI IO explodes or commands are slow."""

    psi_io_threshold: float = 25.0
    command_slow_ms: float = 2000.0
    heavy_cooldown_seconds: float = 30.0
    mode: str = "full"  # full | minimal_survival
    _slow_streak: int = 0
    _last_reason: str = ""
    _disabled_tools: set[str] = field(default_factory=set)
    _heavy_active: str | None = None
    _heavy_cooldown_until: float = 0.0
    _last_heavy_timed_out: bool = False

    def evaluate(self, snapshot: dict[str, Any], last_command_ms: float | None = None) -> str:
        pressure = snapshot.get("pressure", {})
        psi_io = _psi_avg10(pressure, "io")
        psi_cpu = _psi_avg10(pressure, "cpu")

        if psi_io is not None and psi_io >= self.psi_io_threshold:
            self._enter_minimal(f"PSI IO avg10={psi_io:.1f}")
            return self.mode

        if psi_cpu is not None and psi_cpu >= self.psi_io_threshold:
            self._enter_minimal(f"PSI CPU avg10={psi_cpu:.1f}")
            return self.mode

        if last_command_ms is not None and last_command_ms >= self.command_slow_ms:
            self._slow_streak += 1
            if self._slow_streak >= 2:
                self._enter_minimal(f"commands slow ({last_command_ms:.0f}ms)")
        else:
            self._slow_streak = max(0, self._slow_streak - 1)

        return self.mode

    def _enter_minimal(self, reason: str) -> None:
        if self.mode != "minimal_survival":
            logger.warning("Diagnostic budget → MINIMAL SURVIVAL CAPTURE (%s)", reason)
        self.mode = "minimal_survival"
        self._last_reason = reason
        self._disabled_tools = set(HEAVY_TOOLS)

    def allow_heavy_diagnostics(self) -> bool:
        if self.mode != "full":
            return False
        if self._heavy_active is not None:
            return False
        if time.monotonic() < self._heavy_cooldown_until:
            return False
        if self._last_heavy_timed_out:
            return False
        return True

    def acquire_heavy(self, tool: str) -> bool:
        if tool not in HEAVY_TOOLS:
            return True
        if not self.allow_heavy_diagnostics():
            logger.warning("HEAVY_DIAGNOSTIC_SKIPPED tool=%s mode=%s active=%s", tool, self.mode, self._heavy_active)
            return False
        self._heavy_active = tool
        return True

    def release_heavy(self, tool: str, *, timed_out: bool = False) -> None:
        if self._heavy_active == tool:
            self._heavy_active = None
        if timed_out:
            self._last_heavy_timed_out = True
            self._heavy_cooldown_until = time.monotonic() + self.heavy_cooldown_seconds
            logger.warning("HEAVY_DIAGNOSTIC_TIMEOUT tool=%s cooldown=%.0fs", tool, self.heavy_cooldown_seconds)
        else:
            self._last_heavy_timed_out = False

    def filter_emergency_commands(
        self,
        commands: list[tuple[str, list[str]]],
    ) -> list[tuple[str, list[str]]]:
        if self.mode == "full":
            return commands
        allowed_prefixes = ("ps_", "top_", "vmstat", "proc_", "dmesg", "journalctl")
        return [c for c in commands if c[0].startswith(allowed_prefixes) or c[0] in ("dmesg", "journalctl")]

    def status(self) -> dict[str, Any]:
        return {
            "mode": self.mode,
            "reason": self._last_reason,
            "disabled_tools": sorted(self._disabled_tools),
            "heavy_active": self._heavy_active,
            "heavy_cooldown_until": self._heavy_cooldown_until,
            "last_heavy_timed_out": self._last_heavy_timed_out,
        }


def _psi_avg10(pressure: Any, resource: str) -> float | None:
    if not isinstance(pressure, dict):
        return None
    parsed = pressure.get("parsed")
    if isinstance(parsed, dict):
        block = parsed.get(resource)
        if isinstance(block, dict):
            if block.get("avg10") is not None:
                return float(block["avg10"])
            some = block.get("some")
            if isinstance(some, dict) and some.get("avg10") is not None:
                return float(some["avg10"])
            if block.get("some_avg10") is not None:
                return float(block["some_avg10"])
    return None
