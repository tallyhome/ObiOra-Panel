"""Tests for root cause / storage I/O stall classification."""

from __future__ import annotations

from crashhunter.report.diagnosis import DiagnosisEngine


def _corpus(*lines: str) -> dict:
    return {
        "kernel_events": list(lines),
        "systemd_events": [],
        "vm_events": [],
        "last_snapshots": [],
        "top_suspicious_events": [],
    }


def test_rcu_stall_alone_no_storage_io_stall() -> None:
    engine = DiagnosisEngine()
    result = engine.analyze(_corpus("rcu_preempt detected stalls on CPUs/tasks: cpu=1"))
    assert result["primary_cause"]["category"] == "rcu_stall"
    assert result["primary_cause"]["confidence"] <= 0.80
    assert "storage_io_stall" not in [f["category"] for f in result["findings"][:1]]


def test_storage_io_stall_with_rcu_secondary() -> None:
    engine = DiagnosisEngine()
    corpus = _corpus(
        "xfsaild/sdb3 state:D wchan=blkdev_issue_flush",
        "task journald blocked for more than 123 seconds",
        "workqueue flush-8:16 blocked",
        "xfs_file_fsync stack trace",
        "xfs_buf_ioend_work pending",
        "15 xfs_end_io pending",
        "rcu_preempt detected stalls on CPUs/tasks",
    )
    events = [
        {"event": "iowait_increased", "timestamp_utc": "2026-07-12T02:48:24.000000Z"},
        {"event": "d_state_processes", "timestamp_utc": "2026-07-12T02:48:25.000000Z"},
        {"event": "rcu_stall", "timestamp_utc": "2026-07-12T02:48:36.000000Z"},
    ]
    result = engine.analyze(
        corpus,
        events=events,
        triggers=["iowait_high", "d_state_processes", "rcu_stall"],
    )
    assert result["primary_cause"]["category"] == "storage_io_stall"
    assert result["primary_cause"]["confidence"] >= 0.90
    assert result["primary_cause"]["device"] == "sdb3"
    assert "rcu_stall" in str(result.get("secondary_effects", [])).lower() or any(
        "RCU" in s for s in result.get("secondary_effects", [])
    )
    assert len(result.get("evidence", [])) >= 3


def test_network_driver_priority_over_storage() -> None:
    engine = DiagnosisEngine()
    corpus = _corpus(
        "NETDEV WATCHDOG: eth0: transmit timed out",
        "ixgbe 0000:03:00.0 eth0: NIC Link is Down",
        "rcu stall detected",
        "xfsaild/sdb3 state:D",
    )
    result = engine.analyze(corpus, triggers=["rcu_stall"])
    assert result["primary_cause"]["category"] == "network_driver"


def test_nvme_timeout_storage_with_evidence() -> None:
    engine = DiagnosisEngine()
    corpus = _corpus(
        "nvme nvme0: I/O timeout, resetting controller",
        "blk_mq_timeout_work: tag set timeout",
    )
    result = engine.analyze(corpus)
    categories = [f["category"] for f in result["findings"]]
    assert "nvme_reset" in categories or (
        result.get("primary_cause", {}).get("category") == "storage_io_stall"
    )
    assert result["findings"][0].get("evidence") or result.get("evidence")


def test_call_trace_alone_not_driver_crash() -> None:
    engine = DiagnosisEngine()
    corpus = _corpus(
        "INFO: task xfsaild blocked for more than 120 seconds.",
        "Call Trace:",
        " [<ffffffff>] io_schedule+0x4a/0x80",
    )
    result = engine.analyze(corpus)
    top = result["findings"][0]["category"]
    assert top != "driver_crash" or result["primary_cause"]["category"] == "storage_io_stall"
