"""Tests for event timeline."""

from __future__ import annotations

import re
import tempfile
from pathlib import Path

from crashhunter.report.event_timeline import EventTimeline


def test_timeline_microsecond_precision() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        tl = EventTimeline(Path(tmp) / "events.jsonl")
        entry = tl.record("test_event", "CPU normal", severity="info")
        assert "timestamp_utc" in entry
        assert entry["timestamp_utc"].endswith("Z")


def test_timeline_persists_and_loads() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        path = Path(tmp) / "events.jsonl"
        tl1 = EventTimeline(path)
        tl1.record("iowait_increased", "IOWait 45%", severity="high")
        tl2 = EventTimeline(path)
        events = tl2.get_events()
        assert len(events) == 1
        assert events[0]["event"] == "iowait_increased"


def test_chronological_narrative() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        tl = EventTimeline(Path(tmp) / "events.jsonl")
        tl.record("cpu_normal", "CPU normal", severity="info")
        tl.record("ssh_timeout", "SSH timeout", severity="critical")
        narrative = tl.get_chronological_narrative()
        assert len(narrative) == 2
        assert "SSH timeout" in narrative[1]
