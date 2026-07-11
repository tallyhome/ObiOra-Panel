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
    min_crashes_for_ml: int = 20


@dataclass
class RingSettings:
    use_tmpfs: bool = True
    tmpfs_path: str = "/dev/shm/crashhunter-ring"
    tmpfs_size_mb: int = 128
    sync_interval_seconds: float = 30.0


@dataclass
class WitnessSettings:
    enabled: bool = False
    receiver_url: str = "https://panel.example.com:9876"
    token: str = ""
    host_id: str = ""
    send_timeout_seconds: float = 3.0
    listen_host: str = "0.0.0.0"
    listen_port: int = 9876
    monitor_enabled: bool = True
    timeout_seconds: float = 15.0
    death_threshold_seconds: float = 30.0
    check_interval_seconds: float = 5.0


@dataclass
class SysRqSettings:
    enabled: bool = True
    auto_on_incident: bool = True
    watchdog_sequence: bool = True
    sequence_wait_seconds: float = 2.0
    trigger_after_seconds: float = 10.0


@dataclass
class NetconsoleSettings:
    enabled: bool = False
    local_ip: str = ""
    local_port: int = 6666
    remote_ip: str = ""
    remote_port: int = 6666
    interface: str = "eth0"


@dataclass
class PerfSettings:
    enabled: bool = True
    duration_seconds: float = 30.0


@dataclass
class FtraceSettings:
    enabled: bool = True
    duration_seconds: float = 10.0
    function_graph_max_seconds: float = 4.0
    irqsoff_max_seconds: float = 3.0
    preemptoff_max_seconds: float = 3.0
    wakeup_max_seconds: float = 3.0
    lock_timeout_seconds: float = 0.5
    buffer_size_kb: int = 256
    trace_read_max_bytes: int = 500_000
    max_graph_functions: int = 24
    watchdog_enabled: bool = True


@dataclass
class QemuGdbSettings:
    enabled: bool = True
    timeout_seconds: float = 15.0
    max_processes: int = 3


@dataclass
class BenchmarkSettings:
    enabled: bool = True
    run_fio: bool = True
    run_stress_ng: bool = True
    run_smart: bool = True
    ping_target: str = "1.1.1.1"
    max_duration_seconds: float = 120.0


@dataclass
class WebSettings:
    enabled: bool = False
    listen_host: str = "127.0.0.1"
    listen_port: int = 8765


@dataclass
class PanelSettings:
    enabled: bool = False
    url: str = ""
    server_id: int = 0
    agent_token: str = ""
    push_interval_seconds: float = 30.0
    snapshot_batch_size: int = 5


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


@dataclass
class DiagnosticBudgetSettings:
    enabled: bool = True
    psi_io_threshold: float = 25.0
    command_slow_ms: float = 2000.0
    heavy_cooldown_seconds: float = 30.0


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
        "libvirt", "qemu", "blkmq", "scheduler", "interrupt", "softirq",
        "pressure", "watchdog", "journal", "dmesg",
        "ipmi", "ipmi_flight", "edac_mce", "smart", "raid", "temperature", "pci",
        "ssh", "ping", "pstore", "vm_heartbeat", "ebpf",
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
    ring: RingSettings = field(default_factory=RingSettings)
    witness: WitnessSettings = field(default_factory=WitnessSettings)
    sysrq: SysRqSettings = field(default_factory=SysRqSettings)
    netconsole: NetconsoleSettings = field(default_factory=NetconsoleSettings)
    perf: PerfSettings = field(default_factory=PerfSettings)
    ftrace: FtraceSettings = field(default_factory=FtraceSettings)
    qemu_gdb: QemuGdbSettings = field(default_factory=QemuGdbSettings)
    benchmark: BenchmarkSettings = field(default_factory=BenchmarkSettings)
    web: WebSettings = field(default_factory=WebSettings)
    panel: PanelSettings = field(default_factory=PanelSettings)
    self_monitor: SelfMonitorSettings = field(default_factory=SelfMonitorSettings)
    plugin_health: PluginHealthSettings = field(default_factory=PluginHealthSettings)
    ebpf: EbpfSettings = field(default_factory=EbpfSettings)
    retention: RetentionSettings = field(default_factory=RetentionSettings)
    prometheus: PrometheusSettings = field(default_factory=PrometheusSettings)
    diagnostic_budget: DiagnosticBudgetSettings = field(default_factory=DiagnosticBudgetSettings)
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
    def ring_tmpfs_dir(self) -> Path:
        return Path(self.ring.tmpfs_path)

    @property
    def ring_sync_dir(self) -> Path:
        return self.data_dir / "ring-persist"

    @property
    def effective_ring_dir(self) -> Path:
        if self.ring.use_tmpfs:
            return self.ring_tmpfs_dir
        return self.ring_dir

    @property
    def witness_data_dir(self) -> Path:
        return self.base_dir / "witness"

    @property
    def psi_history_file(self) -> Path:
        return self.state_dir / "psi_history.json"

    @property
    def benchmark_state_file(self) -> Path:
        return self.state_dir / "benchmark_history.json"

    @property
    def diagnostics_dir(self) -> Path:
        return self.base_dir / "diagnostics"

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
    def sequence_file(self) -> Path:
        return self.state_dir / "sequence.json"

    @property
    def sequence_tmpfs_file(self) -> Path:
        return Path("/dev/shm/crashhunter-sequence.json")

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
            self.ring_sync_dir,
            self.incident_dir,
            self.state_dir,
            self.reports_dir,
            self.logs_dir,
            self.bundles_dir,
            self.witness_data_dir,
            self.diagnostics_dir,
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
    ring_raw = _deep_get(yaml_data, "ring", default={}) or {}
    witness_raw = _deep_get(yaml_data, "witness", default={}) or {}
    sysrq_raw = _deep_get(yaml_data, "sysrq", default={}) or {}
    netconsole_raw = _deep_get(yaml_data, "netconsole", default={}) or {}
    perf_raw = _deep_get(yaml_data, "perf", default={}) or {}
    ftrace_raw = _deep_get(yaml_data, "ftrace", default={}) or {}
    qemu_gdb_raw = _deep_get(yaml_data, "qemu_gdb", default={}) or {}
    benchmark_raw = _deep_get(yaml_data, "benchmark", default={}) or {}
    web_raw = _deep_get(yaml_data, "web", default={}) or {}
    panel_raw = _deep_get(yaml_data, "panel", default={}) or {}
    budget_raw = _deep_get(yaml_data, "diagnostic_budget", default={}) or {}
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
            min_crashes_for_ml=int(similarity_raw.get("min_crashes_for_ml", 20)),
        ),
        ring=RingSettings(
            use_tmpfs=bool(ring_raw.get("use_tmpfs", True)),
            tmpfs_path=str(ring_raw.get("tmpfs_path", "/dev/shm/crashhunter-ring")),
            tmpfs_size_mb=int(ring_raw.get("tmpfs_size_mb", 128)),
            sync_interval_seconds=float(ring_raw.get("sync_interval_seconds", 30.0)),
        ),
        witness=WitnessSettings(
            enabled=bool(witness_raw.get("enabled", False)),
            receiver_url=str(witness_raw.get("receiver_url", "https://panel.example.com:9876")),
            token=str(witness_raw.get("token", os.environ.get("CRASHHUNTER_WITNESS_TOKEN", ""))),
            host_id=str(witness_raw.get("host_id", "")),
            send_timeout_seconds=float(witness_raw.get("send_timeout_seconds", 3.0)),
            listen_host=str(witness_raw.get("listen_host", "0.0.0.0")),
            listen_port=int(witness_raw.get("listen_port", 9876)),
            monitor_enabled=bool(witness_raw.get("monitor_enabled", True)),
            timeout_seconds=float(witness_raw.get("timeout_seconds", 15.0)),
            death_threshold_seconds=float(witness_raw.get("death_threshold_seconds", 30.0)),
            check_interval_seconds=float(witness_raw.get("check_interval_seconds", 5.0)),
        ),
        sysrq=SysRqSettings(
            enabled=bool(sysrq_raw.get("enabled", True)),
            auto_on_incident=bool(sysrq_raw.get("auto_on_incident", True)),
            watchdog_sequence=bool(sysrq_raw.get("watchdog_sequence", True)),
            sequence_wait_seconds=float(sysrq_raw.get("sequence_wait_seconds", 2.0)),
            trigger_after_seconds=float(sysrq_raw.get("trigger_after_seconds", 10.0)),
        ),
        netconsole=NetconsoleSettings(
            enabled=bool(netconsole_raw.get("enabled", False)),
            local_ip=str(netconsole_raw.get("local_ip", "")),
            local_port=int(netconsole_raw.get("local_port", 6666)),
            remote_ip=str(netconsole_raw.get("remote_ip", "")),
            remote_port=int(netconsole_raw.get("remote_port", 6666)),
            interface=str(netconsole_raw.get("interface", "eth0")),
        ),
        perf=PerfSettings(
            enabled=bool(perf_raw.get("enabled", True)),
            duration_seconds=float(perf_raw.get("duration_seconds", 30.0)),
        ),
        ftrace=FtraceSettings(
            enabled=bool(ftrace_raw.get("enabled", True)),
            duration_seconds=float(ftrace_raw.get("duration_seconds", 10.0)),
            function_graph_max_seconds=float(ftrace_raw.get("function_graph_max_seconds", 4.0)),
            irqsoff_max_seconds=float(ftrace_raw.get("irqsoff_max_seconds", 3.0)),
            preemptoff_max_seconds=float(ftrace_raw.get("preemptoff_max_seconds", 3.0)),
            wakeup_max_seconds=float(ftrace_raw.get("wakeup_max_seconds", 3.0)),
            lock_timeout_seconds=float(ftrace_raw.get("lock_timeout_seconds", 0.5)),
            buffer_size_kb=int(ftrace_raw.get("buffer_size_kb", 256)),
            trace_read_max_bytes=int(ftrace_raw.get("trace_read_max_bytes", 500_000)),
            max_graph_functions=int(ftrace_raw.get("max_graph_functions", 24)),
            watchdog_enabled=bool(ftrace_raw.get("watchdog_enabled", True)),
        ),
        qemu_gdb=QemuGdbSettings(
            enabled=bool(qemu_gdb_raw.get("enabled", True)),
            timeout_seconds=float(qemu_gdb_raw.get("timeout_seconds", 15.0)),
            max_processes=int(qemu_gdb_raw.get("max_processes", 3)),
        ),
        benchmark=BenchmarkSettings(
            enabled=bool(benchmark_raw.get("enabled", True)),
            run_fio=bool(benchmark_raw.get("run_fio", True)),
            run_stress_ng=bool(benchmark_raw.get("run_stress_ng", True)),
            run_smart=bool(benchmark_raw.get("run_smart", True)),
            ping_target=str(benchmark_raw.get("ping_target", "1.1.1.1")),
            max_duration_seconds=float(benchmark_raw.get("max_duration_seconds", 120.0)),
        ),
        web=WebSettings(
            enabled=bool(web_raw.get("enabled", False)),
            listen_host=str(web_raw.get("listen_host", "127.0.0.1")),
            listen_port=int(web_raw.get("listen_port", 8765)),
        ),
        panel=PanelSettings(
            enabled=bool(panel_raw.get("enabled", False)),
            url=str(panel_raw.get("url", os.environ.get("OBIORA_PANEL_URL", ""))),
            server_id=int(panel_raw.get("server_id", os.environ.get("OBIORA_SERVER_ID", 0) or 0)),
            agent_token=str(panel_raw.get("agent_token", os.environ.get("OBIORA_AGENT_TOKEN", ""))),
            push_interval_seconds=float(panel_raw.get("push_interval_seconds", 30.0)),
            snapshot_batch_size=int(panel_raw.get("snapshot_batch_size", 5)),
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
        diagnostic_budget=DiagnosticBudgetSettings(
            enabled=bool(budget_raw.get("enabled", True)),
            psi_io_threshold=float(budget_raw.get("psi_io_threshold", 25.0)),
            command_slow_ms=float(budget_raw.get("command_slow_ms", 2000.0)),
            heavy_cooldown_seconds=float(budget_raw.get("heavy_cooldown_seconds", 30.0)),
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
