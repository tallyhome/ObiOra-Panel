"""Fail-safe ftrace tests."""

from __future__ import annotations

import json
import os
import threading
import time
from pathlib import Path

import pytest

from crashhunter.config.settings import FtraceSettings
from crashhunter.diagnostics.ftrace import FtraceLockBusy, FtraceRecorder


def _make_tracefs(tmp_path: Path) -> Path:
    root = tmp_path / "tracing"
    root.mkdir()
    for name, default in (
        ("current_tracer", "nop"),
        ("tracing_on", "0"),
        ("trace", ""),
        ("set_graph_function", ""),
        ("set_ftrace_filter", ""),
        ("buffer_size_kb", "256"),
    ):
        (root / name).write_text(default, encoding="ascii")
    (root / "available_filter_functions").write_text(
        "schedule\nschedule_timeout\nmutex_lock\nkvm_vcpu_ioctl\n",
        encoding="utf-8",
    )
    return root


@pytest.fixture
def tracefs(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> Path:
    root = _make_tracefs(tmp_path)
    monkeypatch.setattr("crashhunter.diagnostics.ftrace.TRACING_PATHS", (root,))
    return root


@pytest.fixture
def recorder(tmp_path: Path, tracefs: Path) -> FtraceRecorder:
    settings = FtraceSettings(
        function_graph_max_seconds=0.2,
        irqsoff_max_seconds=0.2,
        lock_timeout_seconds=0.2,
        buffer_size_kb=64,
    )
    return FtraceRecorder(
        tmp_path / "out",
        settings,
        tmp_path / "state",
    )


def test_cleanup_after_success(recorder: FtraceRecorder, tracefs: Path) -> None:
    result = recorder.record("irqsoff")
    assert result["recorded"] is True
    assert (tracefs / "tracing_on").read_text().strip() == "0"
    assert (tracefs / "current_tracer").read_text().strip() == "nop"


def test_cleanup_after_exception(recorder: FtraceRecorder, tracefs: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    def boom(_root: Path, _limit: int) -> str:
        raise OSError("read failed")

    monkeypatch.setattr(recorder, "_read_trace_limited", boom)
    result = recorder.record("irqsoff")
    assert result["recorded"] is False
    assert (tracefs / "tracing_on").read_text().strip() == "0"
    assert (tracefs / "current_tracer").read_text().strip() == "nop"


def test_disable_error_still_resets_tracer(recorder: FtraceRecorder, tracefs: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    original = recorder._write_tracefs

    def flaky_write(root: Path, name: str, value: str) -> None:
        if name == "tracing_on" and value == "0":
            raise OSError("denied")
        original(root, name, value)

    monkeypatch.setattr(recorder, "_write_tracefs", flaky_write)
    recorder.record("irqsoff")
    assert (tracefs / "current_tracer").read_text().strip() == "nop"


def test_function_graph_refused_without_filters(recorder: FtraceRecorder, tracefs: Path) -> None:
    (tracefs / "available_filter_functions").write_text("", encoding="utf-8")
    result = recorder.record("function_graph")
    assert result["reason"] == "no_safe_filter_functions"
    assert result["captured"] is False
    assert (tracefs / "tracing_on").read_text().strip() == "0"


def test_function_graph_uses_allowlist(recorder: FtraceRecorder) -> None:
    result = recorder.record("function_graph")
    assert result["recorded"] is True
    assert result.get("graph_functions")
    assert "all functions enabled" not in " ".join(result["graph_functions"]).lower()


def test_lock_prevents_concurrent_capture(recorder: FtraceRecorder) -> None:
    recorder._acquire_lock()
    try:
        result = recorder.record("irqsoff")
        assert result.get("reason") == "ftrace_capture_already_active"
    finally:
        recorder._release_lock()


def test_abandoned_session_recovered(recorder: FtraceRecorder, tracefs: Path, tmp_path: Path) -> None:
    state = {
        "capture_id": "cap123",
        "owner_pid": 999999,
        "tracer": "function_graph",
        "tracefs_root": str(tracefs),
        "started_at": time.time() - 120,
        "graph_functions": ["schedule"],
    }
    (tmp_path / "state" / "ftrace_session.json").write_text(json.dumps(state), encoding="utf-8")
    (tracefs / "tracing_on").write_text("1", encoding="ascii")
    (tracefs / "current_tracer").write_text("function_graph", encoding="ascii")

    recovery = recorder.recover_abandoned_sessions()
    assert recovery is not None
    assert recovery["recovered"] is True
    assert (tracefs / "tracing_on").read_text().strip() == "0"
    assert (tracefs / "current_tracer").read_text().strip() == "nop"


def test_external_tracer_not_touched_without_state(recorder: FtraceRecorder, tracefs: Path) -> None:
    (tracefs / "tracing_on").write_text("1", encoding="ascii")
    (tracefs / "current_tracer").write_text("function_graph", encoding="ascii")
    recovery = recorder.recover_abandoned_sessions()
    assert recovery is not None
    assert recovery.get("recovered") is False


def test_watchdog_stops_long_capture(recorder: FtraceRecorder, tracefs: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    recorder.settings.watchdog_enabled = True
    recorder.settings.function_graph_max_seconds = 0.05

    def slow_sleep(_duration: float) -> None:
        time.sleep(0.5)

    monkeypatch.setattr(recorder, "_sleep_bounded", slow_sleep)
    recorder.record("irqsoff")
    assert (tracefs / "tracing_on").read_text().strip() == "0"


def test_shutdown_event_skips_capture(recorder: FtraceRecorder) -> None:
    recorder.shutdown_event.set()
    result = recorder.record("irqsoff")
    assert result["reason"] == "shutdown_requested"
