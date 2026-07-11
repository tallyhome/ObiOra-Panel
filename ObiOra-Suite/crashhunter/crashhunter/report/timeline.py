"""Timeline builder for report graphs."""

from __future__ import annotations

from typing import Any


def build_metric_series(correlation: dict[str, Any]) -> dict[str, list[dict[str, Any]]]:
    """Extract time series for charts from correlated timeline."""
    timeline = correlation.get("timeline", [])
    return {
        "cpu": [
            {"t": e.get("timestamp"), "v": e.get("cpu_percent", 0)}
            for e in timeline
        ],
        "memory": [
            {
                "t": e.get("timestamp"),
                "v": e.get("mem_available_kb", 0),
            }
            for e in timeline
        ],
        "load": [
            {
                "t": e.get("timestamp"),
                "v": (e.get("load") or {}).get("load_1", 0),
            }
            for e in timeline
        ],
        "blocked": [
            {"t": e.get("timestamp"), "v": e.get("blocked_tasks", 0)}
            for e in timeline
        ],
        "network": [
            {"t": e.get("timestamp"), "v": e.get("tcp_established", 0)}
            for e in timeline
        ],
        "vms": [
            {"t": e.get("timestamp"), "v": e.get("vm_count", 0)}
            for e in timeline
        ],
    }
