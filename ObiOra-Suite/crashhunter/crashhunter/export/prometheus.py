"""Prometheus metrics export — optional file-based scrape target."""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.prometheus")


class PrometheusExporter:
    """Write Prometheus text format metrics for Grafana scraping."""

    def __init__(self, metrics_file: Path) -> None:
        self.metrics_file = metrics_file

    def export(self, snapshot: dict[str, Any], self_monitor: dict[str, Any]) -> None:
        lines: list[str] = []
        lines.append("# HELP crashhunter_cpu_percent CPU usage percent")
        lines.append("# TYPE crashhunter_cpu_percent gauge")
        cpu = snapshot.get("cpu", {}).get("total_percent", 0)
        lines.append(f"crashhunter_cpu_percent {cpu}")

        lines.append("# HELP crashhunter_iowait_percent IO wait percent")
        lines.append("# TYPE crashhunter_iowait_percent gauge")
        iowait = snapshot.get("cpu", {}).get("iowait_percent", 0)
        lines.append(f"crashhunter_iowait_percent {iowait}")

        lines.append("# HELP crashhunter_mem_available_kb Memory available kB")
        lines.append("# TYPE crashhunter_mem_available_kb gauge")
        mem = snapshot.get("memory", {}).get("mem_available_kb", 0)
        lines.append(f"crashhunter_mem_available_kb {mem}")

        lines.append("# HELP crashhunter_dstate_count D-state process count")
        lines.append("# TYPE crashhunter_dstate_count gauge")
        dstate = snapshot.get("dstate", {}).get("count", 0)
        lines.append(f"crashhunter_dstate_count {dstate}")

        lines.append("# HELP crashhunter_cycle_duration_ms Last collection cycle ms")
        lines.append("# TYPE crashhunter_cycle_duration_ms gauge")
        lines.append(f"crashhunter_cycle_duration_ms {snapshot.get('collection_duration_ms', 0)}")

        lines.append("# HELP crashhunter_self_memory_mb CrashHunter RSS MB")
        lines.append("# TYPE crashhunter_self_memory_mb gauge")
        lines.append(f"crashhunter_self_memory_mb {self_monitor.get('memory_rss_mb', 0)}")

        for name, duration in snapshot.get("collector_durations_ms", {}).items():
            safe = name.replace("-", "_").replace(".", "_")
            lines.append(f'crashhunter_plugin_duration_ms{{plugin="{safe}"}} {duration}')

        try:
            self.metrics_file.parent.mkdir(parents=True, exist_ok=True)
            self.metrics_file.write_text("\n".join(lines) + "\n", encoding="utf-8")
        except OSError as exc:
            logger.warning("Prometheus export failed: %s", exc)
