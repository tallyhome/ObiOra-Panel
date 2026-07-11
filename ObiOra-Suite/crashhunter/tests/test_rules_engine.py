"""Tests for rules engine."""

from __future__ import annotations

import tempfile
from pathlib import Path

from crashhunter.analysis.rules_engine import RulesEngine


def test_rules_engine_triggers_iowait() -> None:
    engine = RulesEngine()
    snapshot = {"cpu": {"iowait_percent": 45.0}}
    triggered = engine.evaluate(snapshot)
    assert any(t["rule_id"] == "iowait_spike" for t in triggered)


def test_rules_engine_custom_yaml() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        rules_file = Path(tmp) / "rules.yaml"
        rules_file.write_text(
            "rules:\n  - id: test_rule\n    match_field: cpu.total_percent\n"
            "    operator: gt\n    threshold: 90\n    severity: high\n"
            "    event: cpu_high\n    message: CPU high\n",
            encoding="utf-8",
        )
        engine = RulesEngine(rules_path=rules_file)
        triggered = engine.evaluate({"cpu": {"total_percent": 95}})
        assert len(triggered) == 1
        assert triggered[0]["event"] == "cpu_high"
