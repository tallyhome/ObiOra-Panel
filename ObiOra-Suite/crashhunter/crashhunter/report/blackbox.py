"""
Black Box Flight Recorder — aviation-style pre-crash memory.

Keeps the last 60 minutes (720 × 5s) in a circular in-memory buffer,
synced to disk each sample. On reboot after a freeze, correlates the
timeline and feeds the diagnosis engine.
"""

from __future__ import annotations

import json
import logging
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from crashhunter.storage.ring_buffer import RingBuffer
from crashhunter.utils.types import SuspiciousEvent

logger = logging.getLogger("crashhunter.blackbox")


class BlackBoxRecorder:
    """In-memory flight recorder mirrored to persistent ring storage."""

    def __init__(self, ring: RingBuffer, memory_file: Path) -> None:
        self.ring = ring
        self.memory_file = memory_file
        self._flight_log: list[dict[str, Any]] = []

    def record(self, snapshot: dict[str, Any]) -> None:
        """Store snapshot in ring buffer and append correlated flight events."""
        self.ring.append(snapshot)
        events = self._extract_events(snapshot)
        self._flight_log.extend(events)
        if len(self._flight_log) > 5000:
            self._flight_log = self._flight_log[-5000:]
        self._persist_memory_index(snapshot)

    def _persist_memory_index(self, snapshot: dict[str, Any]) -> None:
        index = {
            "last_timestamp": snapshot.get("system", {}).get("timestamp"),
            "ring_count": self.ring.count,
            "event_count": len(self._flight_log),
        }
        try:
            self.memory_file.write_text(json.dumps(index), encoding="utf-8")
        except OSError as exc:
            logger.warning("Black box index write failed: %s", exc)

    def correlate(self) -> dict[str, Any]:
        """Build correlated timeline from all ring snapshots after reboot."""
        snapshots = self.ring.get_all_ordered()
        if not snapshots:
            snapshots = self._load_orphan_snapshots()

        timeline: list[dict[str, Any]] = []
        kernel_events: list[str] = []
        vm_events: list[str] = []
        systemd_events: list[str] = []
        anomaly_scores: list[SuspiciousEvent] = []

        for snap in snapshots:
            ts = snap.get("system", {}).get("timestamp", "unknown")
            entry = {
                "timestamp": ts,
                "uptime": snap.get("system", {}).get("uptime_seconds"),
                "load": snap.get("system", {}).get("loadavg", {}),
                "cpu_percent": snap.get("cpu", {}).get("total_percent", 0),
                "mem_available_kb": snap.get("memory", {}).get("mem_available_kb", 0),
                "blocked_tasks": snap.get("cpu", {}).get("blocked_tasks", 0),
                "vm_count": snap.get("virtualizor", {}).get("vm_count", 0),
                "tcp_established": snap.get("network", {}).get("tcp_established", 0),
            }
            timeline.append(entry)

            for line in snap.get("kernel", {}).get("dmesg_diff", []):
                kernel_events.append(f"[{ts}] {line}")
            for line in snap.get("kernel", {}).get("journal_diff", []):
                systemd_events.append(f"[{ts}] {line}")
            virt = snap.get("virtualizor", {}).get("virsh_list", "")
            if virt:
                vm_events.append(f"[{ts}] VMs:\n{virt[:200]}")

            anomaly_scores.extend(self._score_snapshot(snap, ts))

        anomaly_scores.sort(key=lambda e: e["probability"], reverse=True)

        return {
            "recorder": "BlackBox Flight Recorder",
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "snapshot_count": len(snapshots),
            "duration_minutes": round(len(snapshots) * 5 / 60, 1),
            "timeline": timeline,
            "kernel_events": kernel_events[-200:],
            "vm_events": vm_events[-100:],
            "systemd_events": systemd_events[-200:],
            "top_suspicious_events": anomaly_scores[:20],
            "last_snapshots": snapshots[-5:],
        }

    def _load_orphan_snapshots(self) -> list[dict[str, Any]]:
        return self.ring.get_all_ordered()

    def _extract_events(self, snapshot: dict[str, Any]) -> list[dict[str, Any]]:
        events: list[dict[str, Any]] = []
        ts = snapshot.get("system", {}).get("timestamp", "")
        for line in snapshot.get("kernel", {}).get("dmesg_diff", []):
            events.append({"timestamp": ts, "source": "dmesg", "message": line})
        for line in snapshot.get("kernel", {}).get("journal_diff", []):
            events.append({"timestamp": ts, "source": "journal", "message": line})
        blocked = snapshot.get("cpu", {}).get("blocked_tasks", 0)
        if blocked and blocked > 10:
            events.append(
                {
                    "timestamp": ts,
                    "source": "scheduler",
                    "message": f"High blocked tasks: {blocked}",
                }
            )
        return events

    def _score_snapshot(self, snapshot: dict[str, Any], ts: str) -> list[SuspiciousEvent]:
        scored: list[SuspiciousEvent] = []
        cpu = snapshot.get("cpu", {}).get("total_percent", 0)
        if cpu > 95:
            scored.append(
                {
                    "timestamp": ts,
                    "event": "cpu_saturation",
                    "source": "cpu",
                    "probability": min(0.95, cpu / 100),
                    "detail": f"CPU at {cpu}%",
                }
            )
        mem_avail = snapshot.get("memory", {}).get("mem_available_kb", 0)
        mem_total = snapshot.get("memory", {}).get("mem_total_kb", 1)
        if mem_total and mem_avail / mem_total < 0.05:
            scored.append(
                {
                    "timestamp": ts,
                    "event": "memory_pressure",
                    "source": "memory",
                    "probability": 0.85,
                    "detail": f"MemAvailable {mem_avail} kB / {mem_total} kB",
                }
            )
        for line in snapshot.get("kernel", {}).get("dmesg_diff", []):
            lower = line.lower()
            if any(k in lower for k in ("oom", "panic", "stall", "hung", "watchdog")):
                scored.append(
                    {
                        "timestamp": ts,
                        "event": "kernel_anomaly",
                        "source": "dmesg",
                        "probability": 0.9,
                        "detail": line[:300],
                    }
                )
        blocked = snapshot.get("cpu", {}).get("blocked_tasks", 0)
        if blocked > 50:
            scored.append(
                {
                    "timestamp": ts,
                    "event": "io_or_lock_contention",
                    "source": "scheduler",
                    "probability": min(0.8, blocked / 100),
                    "detail": f"{blocked} blocked tasks",
                }
            )
        return scored
