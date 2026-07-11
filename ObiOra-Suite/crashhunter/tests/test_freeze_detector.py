"""Tests for silent freeze detector."""

from __future__ import annotations

import tempfile
from pathlib import Path
from unittest.mock import patch

from crashhunter.config.settings import Settings
from crashhunter.freeze.detector import SilentFreezeDetector
from crashhunter.report.event_timeline import EventTimeline


def test_detector_triggers_on_ssh_timeout() -> None:
    settings = Settings()
    with tempfile.TemporaryDirectory() as tmp:
        timeline = EventTimeline(Path(tmp) / "timeline.jsonl")
        detector = SilentFreezeDetector(settings, timeline)
        snapshot = {
            "responsiveness": {
                "ssh_localhost": {"timed_out": True, "ok": False},
                "ping_loopback": {"ok": True, "timed_out": False},
                "ping_external": {"ok": True, "timed_out": False},
                "virsh_list": {"ok": True, "timed_out": False},
                "virtualizor": {"ok": True, "timed_out": False},
                "libvirt": {"connect_ok": True, "timed_out": False},
            },
            "cpu": {"total_percent": 10, "iowait_percent": 5},
            "dstate": {"count": 0, "processes": []},
            "kernel": {"dmesg_diff": [], "journal_diff": []},
            "disk": {"latency": [], "filesystems": []},
            "processes": {"top": []},
        }
        signals = detector.evaluate(snapshot)
        assert any(s.trigger == "ssh_timeout" for s in signals)
        assert detector.should_trigger_incident(signals)


def test_detector_triggers_on_d_state() -> None:
    settings = Settings()
    with tempfile.TemporaryDirectory() as tmp:
        timeline = EventTimeline(Path(tmp) / "timeline.jsonl")
        detector = SilentFreezeDetector(settings, timeline)
        snapshot = {
            "responsiveness": {
                "ssh_localhost": {"ok": True, "timed_out": False},
                "ping_loopback": {"ok": True, "timed_out": False},
                "ping_external": {"ok": True, "timed_out": False},
                "virsh_list": {"ok": True, "timed_out": False},
                "virtualizor": {"ok": True, "timed_out": False},
                "libvirt": {"connect_ok": True, "timed_out": False},
            },
            "cpu": {"total_percent": 10, "iowait_percent": 5},
            "dstate": {
                "count": 2,
                "processes": [
                    {"pid": 100, "comm": "qemu-kvm", "wchan": "wait_on_page"},
                    {"pid": 101, "comm": "dd", "wchan": "io_schedule"},
                ],
            },
            "kernel": {"dmesg_diff": [], "journal_diff": []},
            "disk": {"latency": [], "filesystems": []},
            "processes": {"top": []},
        }
        signals = detector.evaluate(snapshot)
        assert any(s.trigger == "d_state_processes" for s in signals)


def test_detector_triggers_on_high_iowait() -> None:
    settings = Settings()
    with tempfile.TemporaryDirectory() as tmp:
        timeline = EventTimeline(Path(tmp) / "timeline.jsonl")
        detector = SilentFreezeDetector(settings, timeline)
        snapshot = {
            "responsiveness": {
                "ssh_localhost": {"ok": True, "timed_out": False},
                "ping_loopback": {"ok": True, "timed_out": False},
                "ping_external": {"ok": True, "timed_out": False},
                "virsh_list": {"ok": True, "timed_out": False},
                "virtualizor": {"ok": True, "timed_out": False},
                "libvirt": {"connect_ok": True, "timed_out": False},
            },
            "cpu": {"total_percent": 30, "iowait_percent": 45.0},
            "dstate": {"count": 0, "processes": []},
            "kernel": {"dmesg_diff": [], "journal_diff": []},
            "disk": {"latency": [], "filesystems": []},
            "processes": {"top": []},
        }
        signals = detector.evaluate(snapshot)
        assert any(s.trigger == "iowait_high" for s in signals)
