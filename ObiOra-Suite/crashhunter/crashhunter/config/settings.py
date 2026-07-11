"""Crash Hunter configuration — YAML + environment overrides."""

from __future__ import annotations

import os
import platform
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import yaml


@dataclass
class IncidentSettings:
    enabled: bool = True
    emergency_interval_seconds: float = 0.5
    emergency_duration_seconds: float = 60.0
    virsh_timeout_seconds: float = 3.0
    ssh_timeout_seconds: float = 3.0
    ping_timeout_seconds: float = 2.0
    virtualizor_timeout_seconds: float = 5.0
    iowait_threshold_percent: float = 20.0
    blocked_d_state_threshold: int = 1
    command_timeout_count_threshold: int = 2
    clock_drift_threshold_seconds: float = 30.0
    scheduler_stall_cycles: int = 3
    external_ping_target: str = "1.1.1.1"


@dataclass
class ThresholdSettings:
    cpu_saturation_percent: float = 95.0
    memory_pressure_ratio: float = 0.05
    disk_latency_ms: float = 500.0
    load_spike_multiplier: float = 3.0


@dataclass
class SimilaritySettings:
    enabled: bool = True
    min_confidence: float = 0.5
    index_file: str = "similarity_index.json"


@dataclass
class SelfMonitorSettings:
    enabled: bool = True
    max_cycle_duration_ms: float = 4500.0
    max_memory_mb: float = 100.0


@dataclass
class PluginHealthSettings:
    enabled: bool = True
    max_plugin_duration_ms: float = 3000.0
    max_consecutive_failures: int = 5
    max_slow_cycles: int = 3
    cooldown_seconds: float = 300.0


@dataclass
class EbpfSettings:
    enabled: bool = False
    timeout_seconds: float = 2.0


@dataclass
class RetentionSettings:
    enabled: bool = True
    retention_days: int = 90
    compress_after_days: int = 7


@dataclass
class PrometheusSettings:
    enabled: bool = False
    metrics_file: str = "prometheus.metrics"


def _default_hostname() -> str:
    if hasattr(os, "uname"):
        return os.uname().nodename
    return platform.node()


@dataclass
class Settings:
    """Runtime configuration for the Crash Hunter daemon."""

    base_dir: Path = field(default_factory=lambda: Path("/opt/crashhunter"))
    interval_seconds: float = 5.0
    ring_capacity: int = 720
    top_process_count: int = 50
    journal_lines: int = 100
    dmesg_lines: int = 100
    subprocess_timeout: float = 4.0
    log_level: str = "INFO"
    hostname: str = field(default_factory=_default_hostname)
    enabled_collectors: list[str] = field(default_factory=lambda: [
        "system", "cpu", "memory", "disk", "network", "processes",
        "kernel", "virtualizor", "hardware", "responsiveness", "dstate",
        "swap", "oom", "numa", "hugepages", "lvm", "xfs",
        "libvirt", "qemu", "scheduler", "interrupt", "softirq",
        "pressure", "watchdog", "journal", "dmesg",
        "ipmi", "smart", "raid", "temperature", "pci",
        "ssh", "ping", "ebpf",
    ])
    command_thresholds: dict[str, float] = field(default_factory=lambda: {
        "virsh_list": 3000, "ssh_localhost": 3000, "ping_loopback": 2000,
        "ping_external": 2000, "df": 3000, "systemctl": 3000,
        "journalctl": 5000, "lsblk": 3000, "iostat": 5000,
    })
    default_command_threshold_ms: float = 3000.0
    incident: IncidentSettings = field(default_factory=IncidentSettings)
    thresholds: ThresholdSettings = field(default_factory=ThresholdSettings)
    similarity: SimilaritySettings = field(default_factory=SimilaritySettings)
    self_monitor: SelfMonitorSettings = field(default_factory=SelfMonitorSettings)
    plugin_health: PluginHealthSettings = field(default_factory=PluginHealthSettings)
    ebpf: EbpfSettings = field(default_factory=EbpfSettings)
    retention: RetentionSettings = field(default_factory=RetentionSettings)
    prometheus: PrometheusSettings = field(default_factory=PrometheusSettings)
    simulation_enabled: bool = False
    config_path: Path | None = None
    rules_path: Path | None = None

    @property
    def data_dir(self) -> Path:
        return self.base_dir / "data"

    @property
    def ring_dir(self) -> Path:
        return self.data_dir / "ring"

    @property
    def incident_dir(self) -> Path:
        return self.data_dir / "incidents"

    @property
    def state_dir(self) -> Path:
        return self.data_dir / "state"

    @property
    def reports_dir(self) -> Path:
        return self.base_dir / "reports"

    @property
    def logs_dir(self) -> Path:
        return self.base_dir / "logs"

    @property
    def boot_id_file(self) -> Path:
        return self.state_dir / "last_boot_id"

    @property
    def last_uptime_file(self) -> Path:
        return self.state_dir / "last_uptime"

    @property
    def last_clock_file(self) -> Path:
        return self.state_dir / "last_clock"

    @property
    def blackbox_memory_file(self) -> Path:
        return self.state_dir / "blackbox_memory.json"

    @property
    def similarity_index_file(self) -> Path:
        return self.state_dir / self.similarity.index_file

    @property
    def incident_state_file(self) -> Path:
        return self.state_dir / "incident_state.json"

    @property
    def timeline_file(self) -> Path:
        return self.state_dir / "event_timeline.jsonl"

    @property
    def regression_state_file(self) -> Path:
        return self.state_dir / "regression_state.json"

    @property
    def prometheus_metrics_file(self) -> Path:
        return self.base_dir / self.prometheus.metrics_file

    @property
    def bundles_dir(self) -> Path:
        return self.base_dir / "bundles"

    def ring_capacity_for_interval(self) -> int:
        """60 minutes of history at configured interval."""
        return int(3600 / max(self.interval_seconds, 1))

    def ensure_directories(self) -> None:
        for path in (
            self.base_dir,
            self.data_dir,
            self.ring_dir,
            self.incident_dir,
            self.state_dir,
            self.reports_dir,
            self.logs_dir,
            self.bundles_dir,
        ):
            path.mkdir(parents=True, exist_ok=True)


def _deep_get(data: dict[str, Any], *keys: str, default: Any = None) -> Any:
    current: Any = data
    for key in keys:
        if not isinstance(current, dict):
            return default
        current = current.get(key, default)
    return current


def _load_yaml(path: Path) -> dict[str, Any]:
    if not path.exists():
        return {}
    with path.open(encoding="utf-8") as fh:
        return yaml.safe_load(fh) or {}


def load_settings(config_path: Path | None = None) -> Settings:
    """Load settings: default.yaml < config.yaml < environment variables."""
    pkg_default = Path(__file__).parent / "default.yaml"
    yaml_data = _load_yaml(pkg_default)

    env_config = os.environ.get("CRASHHUNTER_CONFIG")
    if config_path is None and env_config:
        config_path = Path(env_config)
    if config_path is None:
        base_guess = Path(os.environ.get("CRASHHUNTER_BASE", "/opt/crashhunter"))
        user_config = base_guess / "config.yaml"
        if user_config.exists():
            config_path = user_config

    if config_path and config_path.exists():
        yaml_data = _merge_dict(yaml_data, _load_yaml(config_path))

    base = Path(os.environ.get("CRASHHUNTER_BASE", _deep_get(yaml_data, "daemon", "base_dir", default="/opt/crashhunter")))
    hostname = os.environ.get("CRASHHUNTER_HOSTNAME") or _deep_get(yaml_data, "daemon", "hostname") or _default_hostname()

    incident_raw = _deep_get(yaml_data, "incident", default={}) or {}
    thresholds_raw = _deep_get(yaml_data, "thresholds", default={}) or {}
    similarity_raw = _deep_get(yaml_data, "similarity", default={}) or {}
    self_raw = _deep_get(yaml_data, "self_monitor", default={}) or {}
    plugin_raw = _deep_get(yaml_data, "plugin_health", default={}) or {}
    ebpf_raw = _deep_get(yaml_data, "ebpf", default={}) or {}
    retention_raw = _deep_get(yaml_data, "retention", default={}) or {}
    prom_raw = _deep_get(yaml_data, "prometheus", default={}) or {}
    cmd_thresh_raw = _deep_get(yaml_data, "command_thresholds", default={}) or {}
    collectors_raw = _deep_get(yaml_data, "collectors", "enabled", default=None)

    interval = float(os.environ.get("CRASHHUNTER_INTERVAL", _deep_get(yaml_data, "daemon", "interval_seconds", default=5.0)))
    ring_cap_env = os.environ.get("CRASHHUNTER_RING_CAPACITY")
    ring_cap_yaml = int(_deep_get(yaml_data, "ring", "capacity", default=0) or 0)
    if ring_cap_env:
        ring_capacity = int(ring_cap_env)
    elif ring_cap_yaml > 0:
        ring_capacity = ring_cap_yaml
    else:
        ring_capacity = int(3600 / max(interval, 1))

    rules_path_cfg = _deep_get(yaml_data, "rules", "path")
    rules_path = Path(rules_path_cfg) if rules_path_cfg else None

    default_collectors = Settings().enabled_collectors
    cmd_thresholds = dict(Settings().command_thresholds)
    cmd_thresholds.update({k: float(v) for k, v in cmd_thresh_raw.items()})

    settings = Settings(
        base_dir=base,
        interval_seconds=interval,
        ring_capacity=ring_capacity,
        top_process_count=int(_deep_get(yaml_data, "sampling", "top_process_count", default=50)),
        journal_lines=int(_deep_get(yaml_data, "sampling", "journal_lines", default=100)),
        dmesg_lines=int(_deep_get(yaml_data, "sampling", "dmesg_lines", default=100)),
        subprocess_timeout=float(_deep_get(yaml_data, "sampling", "subprocess_timeout", default=4.0)),
        log_level=os.environ.get("CRASHHUNTER_LOG_LEVEL", _deep_get(yaml_data, "daemon", "log_level", default="INFO")),
        hostname=hostname,
        enabled_collectors=list(collectors_raw) if collectors_raw else default_collectors,
        command_thresholds=cmd_thresholds,
        default_command_threshold_ms=float(_deep_get(yaml_data, "command_thresholds", "default_ms", default=3000.0)),
        incident=IncidentSettings(
            enabled=bool(incident_raw.get("enabled", True)),
            emergency_interval_seconds=float(incident_raw.get("emergency_interval_seconds", 0.5)),
            emergency_duration_seconds=float(incident_raw.get("emergency_duration_seconds", 60.0)),
            virsh_timeout_seconds=float(incident_raw.get("virsh_timeout_seconds", 3.0)),
            ssh_timeout_seconds=float(incident_raw.get("ssh_timeout_seconds", 3.0)),
            ping_timeout_seconds=float(incident_raw.get("ping_timeout_seconds", 2.0)),
            virtualizor_timeout_seconds=float(incident_raw.get("virtualizor_timeout_seconds", 5.0)),
            iowait_threshold_percent=float(incident_raw.get("iowait_threshold_percent", 20.0)),
            blocked_d_state_threshold=int(incident_raw.get("blocked_d_state_threshold", 1)),
            command_timeout_count_threshold=int(incident_raw.get("command_timeout_count_threshold", 2)),
            clock_drift_threshold_seconds=float(incident_raw.get("clock_drift_threshold_seconds", 30.0)),
            scheduler_stall_cycles=int(incident_raw.get("scheduler_stall_cycles", 3)),
            external_ping_target=str(incident_raw.get("external_ping_target", "1.1.1.1")),
        ),
        thresholds=ThresholdSettings(
            cpu_saturation_percent=float(thresholds_raw.get("cpu_saturation_percent", 95.0)),
            memory_pressure_ratio=float(thresholds_raw.get("memory_pressure_ratio", 0.05)),
            disk_latency_ms=float(thresholds_raw.get("disk_latency_ms", 500.0)),
            load_spike_multiplier=float(thresholds_raw.get("load_spike_multiplier", 3.0)),
        ),
        similarity=SimilaritySettings(
            enabled=bool(similarity_raw.get("enabled", True)),
            min_confidence=float(similarity_raw.get("min_confidence", 0.5)),
            index_file=str(similarity_raw.get("index_file", "similarity_index.json")),
        ),
        self_monitor=SelfMonitorSettings(
            enabled=bool(self_raw.get("enabled", True)),
            max_cycle_duration_ms=float(self_raw.get("max_cycle_duration_ms", 4500.0)),
            max_memory_mb=float(self_raw.get("max_memory_mb", 100.0)),
        ),
        plugin_health=PluginHealthSettings(
            enabled=bool(plugin_raw.get("enabled", True)),
            max_plugin_duration_ms=float(plugin_raw.get("max_plugin_duration_ms", 3000.0)),
            max_consecutive_failures=int(plugin_raw.get("max_consecutive_failures", 5)),
            max_slow_cycles=int(plugin_raw.get("max_slow_cycles", 3)),
            cooldown_seconds=float(plugin_raw.get("cooldown_seconds", 300.0)),
        ),
        ebpf=EbpfSettings(
            enabled=bool(ebpf_raw.get("enabled", False)),
            timeout_seconds=float(ebpf_raw.get("timeout_seconds", 2.0)),
        ),
        retention=RetentionSettings(
            enabled=bool(retention_raw.get("enabled", True)),
            retention_days=int(retention_raw.get("retention_days", 90)),
            compress_after_days=int(retention_raw.get("compress_after_days", 7)),
        ),
        prometheus=PrometheusSettings(
            enabled=bool(prom_raw.get("enabled", False)),
            metrics_file=str(prom_raw.get("metrics_file", "prometheus.metrics")),
        ),
        simulation_enabled=bool(_deep_get(yaml_data, "simulation", "enabled", default=False)),
        config_path=config_path,
        rules_path=rules_path,
    )
    return settings


def _merge_dict(base: dict[str, Any], override: dict[str, Any]) -> dict[str, Any]:
    result = dict(base)
    for key, value in override.items():
        if key in result and isinstance(result[key], dict) and isinstance(value, dict):
            result[key] = _merge_dict(result[key], value)
        else:
            result[key] = value
    return result
