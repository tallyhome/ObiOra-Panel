"""Tests for SysRq controller."""

from __future__ import annotations

from crashhunter.kernel.sysrq import SysRqController, SysRqSequence


def test_sysrq_disabled_returns_not_sent() -> None:
    ctrl = SysRqController(enabled=False)
    result = ctrl.send("t")
    assert result["sent"] is False
    assert result["reason"] == "disabled"


def test_sysrq_burst_returns_three_steps() -> None:
    ctrl = SysRqController(enabled=False)
    burst = ctrl.diagnostic_burst()
    assert len(burst) == 3
    assert burst[0]["key"] == "t"


def test_sysrq_sequence_should_trigger() -> None:
    ctrl = SysRqController(enabled=False)
    seq = SysRqSequence(ctrl, trigger_after_seconds=10.0)
    assert seq.should_trigger(5.0) is False
    assert seq.should_trigger(15.0) is True
