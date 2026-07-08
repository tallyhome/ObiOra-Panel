"""Lightweight benchmark diagnostic module."""

from __future__ import annotations

import os
import time
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class BenchmarkModule(DiagnosticModule):
    """Run lightweight CPU, RAM and disk micro-benchmarks."""

    name = "benchmark"
    title = "Benchmark"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Run quick in-process benchmarks without modifying the system."""

        cpu_ops_per_sec = self._cpu_microbench()
        ram_mb_per_sec = self._ram_microbench()
        disk_mb_per_sec = self._disk_microbench(context)
        return {
            "metrics": {
                "cpu_ops_per_sec": cpu_ops_per_sec,
                "ram_mb_per_sec": ram_mb_per_sec,
                "disk_mb_per_sec": disk_mb_per_sec,
            }
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build benchmark findings."""

        metrics = raw_data["metrics"]
        return [
            Finding(
                Severity.INFO,
                "Benchmark CPU",
                f"{metrics['cpu_ops_per_sec']:,} operations/seconde (micro-bench).",
                "Comparer avec une baseline historique.",
            ),
            Finding(
                Severity.INFO,
                "Benchmark RAM",
                f"{metrics['ram_mb_per_sec']:.1f} Mo/seconde (allocation memoire).",
                "Comparer avec une baseline historique.",
            ),
            Finding(
                Severity.INFO,
                "Benchmark disque",
                f"{metrics['disk_mb_per_sec']:.1f} Mo/seconde (ecriture cache).",
                "Executer un test IOPS dedie pour le stockage production.",
            ),
        ]

    @staticmethod
    def _cpu_microbench() -> int:
        """Measure simple integer operations per second."""

        started = time.monotonic()
        total = 0
        while time.monotonic() - started < 0.2:
            total += 1
        elapsed = max(time.monotonic() - started, 0.001)
        return int(total / elapsed)

    @staticmethod
    def _ram_microbench() -> float:
        """Measure memory allocation throughput in MB/s."""

        started = time.monotonic()
        size = 0
        while time.monotonic() - started < 0.2:
            block = bytearray(1024 * 1024)
            block[0] = 1
            size += 1
        elapsed = max(time.monotonic() - started, 0.001)
        return size / elapsed

    @staticmethod
    def _disk_microbench(context: dict[str, Any]) -> float:
        """Measure write throughput to cache directory."""

        cache_dir = context.get("cache_dir", "cache")
        os.makedirs(cache_dir, exist_ok=True)
        path = os.path.join(str(cache_dir), ".bench.tmp")
        payload = b"x" * (1024 * 1024)
        started = time.monotonic()
        written = 0
        with open(path, "wb") as handle:
            while time.monotonic() - started < 0.2:
                handle.write(payload)
                written += 1
        elapsed = max(time.monotonic() - started, 0.001)
        try:
            os.remove(path)
        except OSError:
            pass
        return written / elapsed
