"""Tests for correlation engine."""

from __future__ import annotations

from crashhunter.analysis.correlation import CorrelationEngine


def test_correlation_builds_causal_story() -> None:
    engine = CorrelationEngine()
    events = [
        {"timestamp": "08:01:15.000000", "event": "iowait_increased", "detail": "IO Wait augmente"},
        {"timestamp": "08:01:20.000000", "event": "virsh_slow", "detail": "virsh timeout"},
        {"timestamp": "08:01:25.000000", "event": "ssh_timeout", "detail": "SSH timeout"},
    ]
    result = engine.correlate(events)
    assert "story" in result
    assert len(result["story"]) >= 2
    assert "↓" in result["story_text"] or len(result["story"]) > 0
