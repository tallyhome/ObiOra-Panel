"""Incident store atomic write tests."""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from crashhunter.storage.incident_store import IncidentStore


def test_summary_atomic_write_creates_directory(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents")
    incident_id = "Incident_20260711_120000"
    store.mark_active(incident_id)
    store.save_summary(incident_id, {"incident_id": incident_id, "status": "ended"})
    summary_path = tmp_path / "incidents" / incident_id / "summary.json"
    assert summary_path.exists()
    data = json.loads(summary_path.read_text(encoding="utf-8"))
    assert data["status"] == "ended"


def test_summary_fails_without_parent_before_fix_is_handled(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents")
    incident_id = "Incident_missing_dir"
    store.save_summary(incident_id, {"incident_id": incident_id})
    assert (tmp_path / "incidents" / incident_id / "summary.json").exists()


def test_counter_restored_after_restart(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents")
    incident_id = "Incident_20260711_130000"
    store.append(incident_id, {"n": 1})
    store.append(incident_id, {"n": 2})

    store2 = IncidentStore(tmp_path / "incidents")
    path = store2.append(incident_id, {"n": 3})
    assert path.name.endswith("_0002.json")


def test_active_incident_flag(tmp_path: Path) -> None:
    store = IncidentStore(tmp_path / "incidents")
    iid = "Incident_active"
    store.mark_active(iid)
    assert store.is_active(iid)
    store.mark_inactive(iid)
    assert not store.is_active(iid)
