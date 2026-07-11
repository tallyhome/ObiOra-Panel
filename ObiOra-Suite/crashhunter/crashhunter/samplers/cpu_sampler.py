"""CPU usage per core from /proc/stat jiffies."""

from __future__ import annotations

from typing import Any

from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.proc import ProcReader


class CpuSampler(BaseSampler):
    name = "cpu"

    def __init__(self) -> None:
        self._prev: dict[str, list[int]] | None = None

    def sample(self) -> dict[str, Any]:
        raw = self._read_cpu_lines()
        usage: dict[str, float] = {}
        total_usage = 0.0

        iowait_percent = 0.0
        if self._prev is not None:
            for key, jiffies in raw.items():
                prev_jiffies = self._prev.get(key)
                if prev_jiffies and len(prev_jiffies) == len(jiffies):
                    usage[key] = self._usage_percent(prev_jiffies, jiffies)
                    if key == "cpu":
                        iowait_percent = self._iowait_percent(prev_jiffies, jiffies)
            total_usage = usage.get("cpu", 0.0)

        self._prev = raw
        return {
            "per_core": usage,
            "total_percent": round(total_usage, 2),
            "iowait_percent": round(iowait_percent, 2),
            "run_queue": ProcReader.loadavg().get("running", 0),
            "blocked_tasks": ProcReader.stat().get("procs_blocked", 0),
        }

    @staticmethod
    def _read_cpu_lines() -> dict[str, list[int]]:
        result: dict[str, list[int]] = {}
        for line in ProcReader.read_lines("/proc/stat"):
            if not line.startswith("cpu"):
                continue
            parts = line.split()
            key = parts[0].rstrip(":")
            values = [int(p) for p in parts[1:] if p.isdigit()]
            if values:
                result[key] = values
        return result

    @staticmethod
    def _usage_percent(prev: list[int], current: list[int]) -> float:
        deltas = [max(c - p, 0) for p, c in zip(prev, current)]
        total = sum(deltas)
        if total == 0:
            return 0.0
        idle_idx = 3
        iowait_idx = 4 if len(deltas) > 4 else None
        idle = deltas[idle_idx]
        if iowait_idx is not None:
            idle += deltas[iowait_idx]
        busy = total - idle
        return round(busy / total * 100.0, 2)

    @staticmethod
    def _iowait_percent(prev: list[int], current: list[int]) -> float:
        deltas = [max(c - p, 0) for p, c in zip(prev, current)]
        total = sum(deltas)
        if total == 0 or len(deltas) <= 4:
            return 0.0
        return round(deltas[4] / total * 100.0, 2)
