"""Tests for PSI history store."""

from __future__ import annotations

import tempfile
from pathlib import Path

from crashhunter.storage.psi_history import PsiHistoryStore


def test_psi_history_trends() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        store = PsiHistoryStore(Path(tmp) / "psi.json", max_entries=10)
        store.record("t1", {"cpu": {"avg10": 1.0}, "io": {"avg10": 0.5}, "memory": {"avg10": 0.1}})
        store.record("t2", {"cpu": {"avg10": 5.0}, "io": {"avg10": 2.0}, "memory": {"avg10": 0.2}})
        trends = store.get_trends()
        assert trends["samples"] == 2
        assert trends["trends"]["cpu"]["max_avg10"] == 5.0
