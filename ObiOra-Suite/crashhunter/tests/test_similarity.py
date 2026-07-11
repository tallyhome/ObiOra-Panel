"""Tests for crash similarity engine."""

from __future__ import annotations

import tempfile
from pathlib import Path

from crashhunter.config.settings import Settings
from crashhunter.report.similarity import SimilarityEngine


def test_similarity_finds_matching_crashes() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        settings = Settings(base_dir=Path(tmp))
        settings.ensure_directories()
        engine = SimilarityEngine(settings)

        report_a = {
            "report_id": "CrashReport_20260101_100000",
            "diagnosis": {"findings": [{"category": "disk_timeout"}]},
            "blackbox": {
                "timeline": [{"cpu_percent": 80, "blocked_tasks": 20}],
                "top_suspicious_events": [{"event": "io_wait_high"}],
            },
        }
        report_b = {
            "report_id": "CrashReport_20260102_100000",
            "diagnosis": {"findings": [{"category": "disk_timeout"}]},
            "blackbox": {
                "timeline": [{"cpu_percent": 75, "blocked_tasks": 18}],
                "top_suspicious_events": [{"event": "io_wait_high"}],
            },
        }
        engine.index_report(report_a)
        similar = engine.find_similar(report_b)
        assert len(similar) >= 1
        assert similar[0]["confidence"] >= 0.5
        assert similar[0]["report_id"] == "CrashReport_20260101_100000"


def test_fingerprint_hash_stable() -> None:
    settings = Settings()
    engine = SimilarityEngine(settings)
    report = {
        "report_id": "test",
        "diagnosis": {"findings": [{"category": "oom"}]},
        "blackbox": {"timeline": [], "top_suspicious_events": []},
    }
    fp1 = engine.fingerprint(report)
    fp2 = engine.fingerprint(report)
    assert fp1["hash"] == fp2["hash"]
