"""Tests for Black Box Flight Recorder."""

from __future__ import annotations

import tempfile
from pathlib import Path

from crashhunter.report.blackbox import BlackBoxRecorder
from crashhunter.storage.ring_buffer import RingBuffer


def _sample(ts: str, cpu: float = 10.0, blocked: int = 0, dmesg_diff: list[str] | None = None) -> dict:
    return {
        "system": {"timestamp": ts, "uptime_seconds": 100, "loadavg": {"load_1": 1.0}},
        "cpu": {"total_percent": cpu, "blocked_tasks": blocked},
        "memory": {"mem_available_kb": 8000000, "mem_total_kb": 16000000},
        "network": {"tcp_established": 50},
        "virtualizor": {"vm_count": 5, "virsh_list": "id name state"},
        "kernel": {"dmesg_diff": dmesg_diff or [], "journal_diff": []},
    }


def test_blackbox_correlates_timeline() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        ring = RingBuffer(capacity=10, ring_dir=Path(tmp))
        bb = BlackBoxRecorder(ring, Path(tmp) / "index.json")
        bb.record(_sample("2026-01-01T10:00:00Z"))
        bb.record(_sample("2026-01-01T10:00:05Z", cpu=99.0))
        correlation = bb.correlate()
        assert correlation["snapshot_count"] == 2
        assert len(correlation["timeline"]) == 2
        assert len(correlation["top_suspicious_events"]) >= 1


def test_blackbox_scores_kernel_anomaly() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        ring = RingBuffer(capacity=5, ring_dir=Path(tmp))
        bb = BlackBoxRecorder(ring, Path(tmp) / "index.json")
        bb.record(
            _sample(
                "2026-01-01T10:00:00Z",
                dmesg_diff=["watchdog: BUG: soft lockup detected on CPU#3"],
            )
        )
        correlation = bb.correlate()
        events = correlation["top_suspicious_events"]
        assert any("kernel_anomaly" in e["event"] for e in events)
