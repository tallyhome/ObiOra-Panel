"""Tests for v2.2 reliability features."""

from __future__ import annotations

import json
from pathlib import Path

from crashhunter.freeze.diagnostic_budget import DiagnosticBudget
from crashhunter.kernel.pstore import read_pstore_at_boot
from crashhunter.storage.sequence_store import SequenceStore
from crashhunter.witness.store import WitnessStore


def test_pstore_empty_when_missing(tmp_path: Path, monkeypatch) -> None:
    monkeypatch.setattr(
        "crashhunter.kernel.pstore.PSTORE_ROOT",
        tmp_path / "pstore",
    )
    result = read_pstore_at_boot()
    assert result["available"] is False
    assert result["entries"] == []


def test_sequence_store_monotone_and_compare(tmp_path: Path) -> None:
    store = SequenceStore(tmp_path / "sequence.json", tmpfs_path=tmp_path / "seq.tmpfs.json")
    a = store.next_id("heartbeat")
    b = store.next_id("heartbeat")
    assert b == a + 1
    gap = store.compare_with_witness(b + 5)
    assert gap["local_write_likely_dead"] is True
    assert gap["gap"] == 5


def test_sequence_atomic_persist(tmp_path: Path) -> None:
    path = tmp_path / "sequence.json"
    store = SequenceStore(path)
    store.next_id("test", {"x": 1})
    data = json.loads(path.read_text(encoding="utf-8"))
    assert data["sequence_id"] == 1
    assert data["event_type"] == "test"


def test_diagnostic_budget_enters_minimal_on_psi() -> None:
    budget = DiagnosticBudget(psi_io_threshold=20.0)
    budget.evaluate({"pressure": {"parsed": {"io": {"avg10": 30.0}}}})
    assert budget.mode == "minimal_survival"
    assert not budget.allow_heavy_diagnostics()


def test_diagnostic_budget_enters_minimal_on_slow_commands() -> None:
    budget = DiagnosticBudget(command_slow_ms=2000.0)
    budget.evaluate({"pressure": {}}, last_command_ms=2500)
    budget.evaluate({"pressure": {}}, last_command_ms=2500)
    assert budget.mode == "minimal_survival"


def test_witness_store_sequence(tmp_path: Path) -> None:
    store = WitnessStore(tmp_path)
    store.record_heartbeat({"host": "dedi-1", "sequence_id": 42})
    assert store.latest_sequence("dedi-1") == 42
