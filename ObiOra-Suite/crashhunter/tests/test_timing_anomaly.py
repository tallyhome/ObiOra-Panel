"""Collector gap vs clock adjustment tests."""

from __future__ import annotations

import time
from unittest.mock import MagicMock

from crashhunter.config.settings import IncidentSettings, Settings
from crashhunter.freeze.detector import SilentFreezeDetector
from crashhunter.report.event_timeline import EventTimeline


def _detector(interval: float = 5.0, threshold: float = 30.0) -> SilentFreezeDetector:
    settings = MagicMock(spec=Settings)
    settings.interval_seconds = interval
    settings.incident = IncidentSettings(clock_drift_threshold_seconds=threshold)
    timeline = EventTimeline(MagicMock())
    timeline.record = MagicMock(return_value={})
    return SilentFreezeDetector(settings, timeline)


def test_collector_gap_not_clock_adjustment() -> None:
    det = _detector()
    det._state.prev_wall_ns = time.time_ns()
    det._state.prev_mono_ns = time.monotonic_ns()
    time.sleep(0.01)
    # Simulate 3983s gap on both clocks (freeze)
    det._state.prev_wall_ns -= int(3983 * 1_000_000_000)
    det._state.prev_mono_ns -= int(3983 * 1_000_000_000)
    signals = det._check_timing_anomaly({})
    triggers = [s.trigger for s in signals]
    assert "collector_gap" in triggers
    assert "clock_adjustment" not in triggers


def test_clock_adjustment_when_mono_normal() -> None:
    det = _detector()
    det._state.prev_mono_ns = time.monotonic_ns()
    det._state.prev_wall_ns = time.time_ns() - int(3600 * 1_000_000_000)
    signals = det._check_timing_anomaly({})
    triggers = [s.trigger for s in signals]
    assert "clock_adjustment" in triggers
