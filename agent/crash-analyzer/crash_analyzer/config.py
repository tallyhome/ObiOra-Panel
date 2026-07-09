"""Configuration du daemon Crash Analyzer."""

from __future__ import annotations

import json
import os
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any


DEFAULT_CONFIG_PATH = Path("/etc/obiora/crash-analyzer.json")
FALLBACK_CONFIG_PATH = Path(__file__).resolve().parent.parent / "config" / "default.json"


@dataclass
class CrashAnalyzerConfig:
    """Paramètres runtime du collecteur."""

    interval_seconds: int = 5
    history_minutes: int = 60
    storage_backend: str = "sqlite"  # sqlite | postgresql
    sqlite_path: str = "/var/lib/obiora/crash-analyzer/metrics.db"
    postgresql_dsn: str = ""
    panel_url: str = ""
    server_id: str = ""
    agent_token: str = ""
    enabled_collectors: list[str] = field(default_factory=list)
    push_interval_seconds: int = 30
    reports_dir: str = "/var/lib/obiora/crash-analyzer/reports"
    state_file: str = "/var/lib/obiora/crash-analyzer/state.json"
    max_cpu_percent_target: float = 1.0

    @classmethod
    def load(cls, path: Path | None = None) -> "CrashAnalyzerConfig":
        """Charge la configuration depuis un fichier JSON et les variables d'environnement."""
        config_path = path or DEFAULT_CONFIG_PATH
        data: dict[str, Any] = {}

        if config_path.is_file():
            data = json.loads(config_path.read_text(encoding="utf-8"))
        elif FALLBACK_CONFIG_PATH.is_file():
            data = json.loads(FALLBACK_CONFIG_PATH.read_text(encoding="utf-8"))

        env_overrides = {
            "interval_seconds": os.getenv("OBIORA_CRASH_INTERVAL"),
            "history_minutes": os.getenv("OBIORA_CRASH_HISTORY_MINUTES"),
            "storage_backend": os.getenv("OBIORA_CRASH_STORAGE"),
            "sqlite_path": os.getenv("OBIORA_CRASH_SQLITE_PATH"),
            "postgresql_dsn": os.getenv("OBIORA_CRASH_PG_DSN"),
            "panel_url": os.getenv("OBIORA_PANEL_URL"),
            "server_id": os.getenv("OBIORA_SERVER_ID"),
            "agent_token": os.getenv("OBIORA_AGENT_TOKEN"),
            "push_interval_seconds": os.getenv("OBIORA_CRASH_PUSH_INTERVAL"),
        }

        for key, value in env_overrides.items():
            if value is not None and value != "":
                data[key] = value

        enabled = data.get("enabled_collectors")
        if not enabled:
            enabled = [
                "cpu", "memory", "swap", "psi", "disk", "network", "thermal",
                "smart", "edac", "rasdaemon", "journal", "journal_boot", "hardware", "tools",
                "dmesg", "virtualizor", "libvirt", "docker", "systemd", "processes", "irq", "ssh", "load",
            ]

        return cls(
            interval_seconds=int(data.get("interval_seconds", 5)),
            history_minutes=int(data.get("history_minutes", 60)),
            storage_backend=str(data.get("storage_backend", "sqlite")),
            sqlite_path=str(data.get("sqlite_path", "/var/lib/obiora/crash-analyzer/metrics.db")),
            postgresql_dsn=str(data.get("postgresql_dsn", "")),
            panel_url=str(data.get("panel_url", "")),
            server_id=str(data.get("server_id", "")),
            agent_token=str(data.get("agent_token", "")),
            enabled_collectors=list(enabled),
            push_interval_seconds=int(data.get("push_interval_seconds", 30)),
            reports_dir=str(data.get("reports_dir", "/var/lib/obiora/crash-analyzer/reports")),
            state_file=str(data.get("state_file", "/var/lib/obiora/crash-analyzer/state.json")),
            max_cpu_percent_target=float(data.get("max_cpu_percent_target", 1.0)),
        )

    def retention_seconds(self) -> int:
        """Durée de rétention en secondes."""
        return self.history_minutes * 60
