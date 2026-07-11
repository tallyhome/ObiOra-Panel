"""Tests for diagnosis engine."""

from __future__ import annotations

from crashhunter.report.diagnosis import DiagnosisEngine


def test_diagnosis_detects_kernel_panic() -> None:
    engine = DiagnosisEngine()
    correlation = {
        "kernel_events": ["[2026-01-01] Kernel panic - not syncing"],
        "systemd_events": [],
        "vm_events": [],
        "last_snapshots": [],
        "top_suspicious_events": [],
    }
    result = engine.analyze(correlation)
    assert result["verdict"] == "Kernel Panic"
    assert result["confidence"] >= 0.9


def test_diagnosis_detects_oom() -> None:
    engine = DiagnosisEngine()
    correlation = {
        "kernel_events": ["Out of memory: Killed process 1234 (mysqld)"],
        "systemd_events": [],
        "vm_events": [],
        "last_snapshots": [],
        "top_suspicious_events": [],
    }
    result = engine.analyze(correlation)
    assert any(f["category"] == "oom" for f in result["findings"])


def test_diagnosis_unknown_freeze() -> None:
    engine = DiagnosisEngine()
    correlation = {
        "kernel_events": [],
        "systemd_events": [],
        "vm_events": [],
        "last_snapshots": [],
        "top_suspicious_events": [
            {
                "timestamp": "t",
                "event": "cpu_saturation",
                "source": "cpu",
                "probability": 0.7,
                "detail": "high cpu",
            }
        ],
    }
    result = engine.analyze(correlation)
    assert result["verdict"] == "UNKNOWN FREEZE"
    assert len(result["top_suspicious_events"]) == 1
