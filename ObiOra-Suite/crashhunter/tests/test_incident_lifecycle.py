"""Incident store concurrency and lifecycle tests."""

from __future__ import annotations

import json
import threading
from pathlib import Path

import pytest

from crashhunter.storage.incident_store import IncidentLifecycle, IncidentStore


def test_ensure_directory_on_trigger(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents")
    incident_id = "Incident_20260712_044835"
    path = store.ensure_incident_directory(incident_id)
    assert path.exists()
    store.save_summary(incident_id, {"incident_id": incident_id, "status": "ended"})
    assert (path / "summary.json").exists()


def test_concurrent_writers_no_corrupt_json(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents")
    incident_id = "Incident_concurrent"
    store.ensure_incident_directory(incident_id)
    errors: list[Exception] = []

    def writer(n: int) -> None:
        try:
            for i in range(5):
                store.append(incident_id, {"n": n, "i": i})
        except Exception as exc:
            errors.append(exc)

    threads = [threading.Thread(target=writer, args=(t,)) for t in range(10)]
    for th in threads:
        th.start()
    for th in threads:
        th.join(timeout=10)
    assert not errors
    assert store.count(incident_id) >= 10
    for path in (tmp_path / "incidents" / incident_id).glob("*.json"):
        if path.name == "summary.json" or path.name == "lifecycle.json":
            continue
        json.loads(path.read_text(encoding="utf-8"))


def test_active_incident_not_eligible_for_cleanup(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents", retention_grace_seconds=0)
    iid = "Incident_active"
    store.ensure_incident_directory(iid)
    store.mark_active(iid)
    assert not store.can_cleanup(iid)


def test_closed_incident_cleanup_after_grace(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents", retention_grace_seconds=0)
    iid = "Incident_closed"
    store.ensure_incident_directory(iid)
    store.mark_inactive(iid)
    assert store.can_cleanup(iid)


def test_capture_stats_reason_on_zero_snapshots(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents")
    iid = "Incident_empty"
    store.ensure_incident_directory(iid)
    stats = store.capture_stats(iid)
    assert stats["local_snapshots_count"] == 0
    assert stats.get("capture_failure_reason") == "process_stalled_before_snapshot"
