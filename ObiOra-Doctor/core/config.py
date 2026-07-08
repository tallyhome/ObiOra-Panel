"""Configuration loader for Obiora Doctor."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

DEFAULT_CONFIG_PATH = Path(__file__).resolve().parents[1] / "config" / "default.json"


def load_config(path: Path | None = None) -> dict[str, Any]:
    """Load configuration from JSON file.

    Parameters:
        path: Optional config path. Defaults to config/default.json.

    Returns:
        Configuration dictionary.

    Example:
        config = load_config()
        timeout = config["timeout_seconds"]
    """

    config_path = path or DEFAULT_CONFIG_PATH
    if not config_path.exists():
        return _fallback_config()

    with config_path.open(encoding="utf-8") as handle:
        data = json.load(handle)
    return {**_fallback_config(), **data}


def _fallback_config() -> dict[str, Any]:
    """Return built-in defaults when config file is missing."""

    return {
        "version": "0.2.0",
        "timeout_seconds": 8,
        "reports_dir": "reports",
        "cache_dir": "cache",
        "logs_dir": "logs",
        "watch_interval_seconds": 1,
        "watch_history_limit": 3600,
        "report_retention_days": 30,
        "api_host": "127.0.0.1",
        "api_port": 8765,
        "critical_disk_percent": 90,
        "critical_ram_percent": 10,
        "warning_ram_percent": 20,
    }
