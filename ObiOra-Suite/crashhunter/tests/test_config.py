"""Tests for YAML configuration loading."""

from __future__ import annotations

import tempfile
from pathlib import Path

from crashhunter.config.settings import load_settings


def test_load_default_settings() -> None:
    settings = load_settings()
    assert settings.interval_seconds == 5.0
    assert settings.ring_capacity == 720
    assert "responsiveness" in settings.enabled_collectors
    assert settings.incident.emergency_interval_seconds == 0.5


def test_yaml_override() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        config = Path(tmp) / "config.yaml"
        config.write_text(
            "daemon:\n  interval_seconds: 10\nincident:\n  iowait_threshold_percent: 30\n",
            encoding="utf-8",
        )
        settings = load_settings(config_path=config)
        assert settings.interval_seconds == 10.0
        assert settings.incident.iowait_threshold_percent == 30.0
