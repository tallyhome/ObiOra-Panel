"""Diagnostic budget heavy-tool orchestration tests."""

from __future__ import annotations

import time

from crashhunter.freeze.diagnostic_budget import DiagnosticBudget


def test_only_one_heavy_at_a_time() -> None:
    budget = DiagnosticBudget()
    assert budget.acquire_heavy("perf") is True
    assert budget.acquire_heavy("ftrace") is False
    budget.release_heavy("perf")
    assert budget.acquire_heavy("ftrace") is True


def test_heavy_cooldown_after_timeout() -> None:
    budget = DiagnosticBudget(heavy_cooldown_seconds=30.0)
    assert budget.acquire_heavy("perf") is True
    budget.release_heavy("perf", timed_out=True)
    assert budget.acquire_heavy("ftrace") is False


def test_psi_triggers_minimal_with_normalized_avg10() -> None:
    budget = DiagnosticBudget(psi_io_threshold=20.0)
    budget.evaluate({"pressure": {"parsed": {"io": {"some": {"avg10": 30.0}, "avg10": 30.0}}}})
    assert budget.mode == "minimal_survival"
    assert not budget.allow_heavy_diagnostics()
