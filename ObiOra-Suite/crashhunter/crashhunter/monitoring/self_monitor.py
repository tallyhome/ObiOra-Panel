"""CrashHunter self-monitoring."""

from __future__ import annotations

import logging
import resource
import sys
from dataclasses import dataclass, field
from typing import Any

from crashhunter.config.settings import Settings

logger = logging.getLogger("crashhunter.self_monitor")


@dataclass
class SelfMonitorStats:
    cycle_count: int = 0
    total_cycle_ms: float = 0.0
    max_cycle_ms: float = 0.0
    collector_failures: dict[str, int] = field(default_factory=dict)
    collector_durations: dict[str, float] = field(default_factory=dict)
    memory_rss_mb: float = 0.0
    warnings: list[str] = field(default_factory=list)


class SelfMonitor:
    """Monitor CrashHunter's own health — CPU, RAM, collector performance."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.stats = SelfMonitorStats()

    def record_cycle(
        self,
        duration_ms: float,
        collector_failures: dict[str, int],
        collector_durations: dict[str, float],
    ) -> dict[str, Any]:
        self.stats.cycle_count += 1
        self.stats.total_cycle_ms += duration_ms
        self.stats.max_cycle_ms = max(self.stats.max_cycle_ms, duration_ms)
        for name, count in collector_failures.items():
            self.stats.collector_failures[name] = self.stats.collector_failures.get(name, 0) + count
        self.stats.collector_durations = collector_durations
        self.stats.memory_rss_mb = self._memory_mb()
        self.stats.warnings.clear()

        if self.settings.self_monitor.enabled:
            if duration_ms > self.settings.self_monitor.max_cycle_duration_ms:
                msg = f"Cycle took {duration_ms}ms (max {self.settings.self_monitor.max_cycle_duration_ms}ms)"
                self.stats.warnings.append(msg)
                logger.warning(msg)
            if self.stats.memory_rss_mb > self.settings.self_monitor.max_memory_mb:
                msg = f"Memory {self.stats.memory_rss_mb:.1f}MB exceeds limit"
                self.stats.warnings.append(msg)
                logger.warning(msg)
            for name, count in collector_failures.items():
                if count > 0:
                    logger.warning("Collector %s failed %d times", name, count)

        return self.snapshot()

    def snapshot(self) -> dict[str, Any]:
        avg = (
            self.stats.total_cycle_ms / self.stats.cycle_count
            if self.stats.cycle_count else 0
        )
        return {
            "cycle_count": self.stats.cycle_count,
            "avg_cycle_ms": round(avg, 2),
            "max_cycle_ms": round(self.stats.max_cycle_ms, 2),
            "memory_rss_mb": round(self.stats.memory_rss_mb, 2),
            "collector_failures": dict(self.stats.collector_failures),
            "collector_durations_ms": dict(self.stats.collector_durations),
            "warnings": list(self.stats.warnings),
        }

    @staticmethod
    def _memory_mb() -> float:
        if sys.platform == "win32":
            return 0.0
        usage = resource.getrusage(resource.RUSAGE_SELF)
        return usage.ru_maxrss / 1024  # Linux reports KB
