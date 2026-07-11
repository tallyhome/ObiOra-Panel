"""Tests for Remote Witness."""

from __future__ import annotations

import json
import tempfile
from pathlib import Path

from crashhunter.config.settings import Settings, WitnessSettings
from crashhunter.witness.monitor import WitnessMonitor
from crashhunter.witness.sender import WitnessSender
from crashhunter.witness.store import WitnessStore


def test_witness_store_records_heartbeat() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        store = WitnessStore(Path(tmp))
        store.record_heartbeat({"host": "dedie-01", "cpu_percent": 50})
        latest = store.latest_heartbeat("dedie-01")
        assert latest is not None
        assert latest["cpu_percent"] == 50
        assert "dedie-01" in store.list_hosts()


def test_witness_sender_builds_payload() -> None:
    settings = Settings(witness=WitnessSettings(host_id="test-host"))
    sender = WitnessSender(settings)
    payload = sender.build_payload({
        "timestamp_us": "2026-07-11T10:00:00",
        "system": {"uptime_seconds": 3600, "load_1": 2.5, "boot_id": "abc"},
        "cpu": {"total_percent": 45, "iowait_percent": 5},
        "memory": {"used_percent": 60},
        "virtualizor": {"virsh_list": " Id\n 1 vm1\n 2 vm2"},
        "pressure": {"parsed": {"cpu": {"avg10": 1.2}}},
    })
    assert payload["host"] == "test-host"
    assert payload["cpu_percent"] == 45
    assert payload["vm_count"] == 2


def test_witness_monitor_detects_timeout() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        store = WitnessStore(Path(tmp))
        store.record_heartbeat({
            "host": "dedie-01",
            "timestamp": "2020-01-01T00:00:00+00:00",
            "received_at": "2020-01-01T00:00:00+00:00",
        })
        settings = Settings(
            witness=WitnessSettings(timeout_seconds=5, death_threshold_seconds=10),
        )
        monitor = WitnessMonitor(settings, store)
        results = monitor.check_all()
        assert len(results) == 1
        assert results[0]["status"] == "dead"
