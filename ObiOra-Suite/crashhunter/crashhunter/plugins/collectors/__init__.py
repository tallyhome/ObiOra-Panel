"""Enterprise collector plugin registry."""

from __future__ import annotations

from typing import TYPE_CHECKING

from crashhunter.plugins.collectors.ebpf import EbpfCollector
from crashhunter.plugins.collectors.hardware_extended import (
    IpmiCollector,
    PciCollector,
    RaidCollector,
    SmartCollector,
    TemperatureCollector,
)
from crashhunter.plugins.collectors.kernel_extended import (
    DmesgCollector,
    InterruptCollector,
    JournalCollector,
    PressureCollector,
    SchedulerCollector,
    SoftirqCollector,
    WatchdogCollector,
)
from crashhunter.plugins.collectors.memory_extended import (
    HugePagesCollector,
    NumaCollector,
    OomCollector,
    SwapCollector,
)
from crashhunter.plugins.collectors.probes_extended import PingCollector, SshCollector
from crashhunter.plugins.collectors.storage_extended import LvmCollector, XfsCollector
from crashhunter.plugins.collectors.virt_extended import LibvirtCollector, QemuCollector

if TYPE_CHECKING:
    from crashhunter.config.settings import Settings
    from crashhunter.plugins.base import BaseCollector
    from crashhunter.utils.subprocess_runner import SubprocessRunner

# All enterprise plugin classes — each is independently enable/disable via YAML
ENTERPRISE_COLLECTOR_CLASSES: list[type] = [
    SwapCollector,
    OomCollector,
    NumaCollector,
    HugePagesCollector,
    LvmCollector,
    XfsCollector,
    LibvirtCollector,
    QemuCollector,
    SchedulerCollector,
    InterruptCollector,
    SoftirqCollector,
    PressureCollector,
    WatchdogCollector,
    JournalCollector,
    DmesgCollector,
    IpmiCollector,
    SmartCollector,
    RaidCollector,
    TemperatureCollector,
    PciCollector,
    SshCollector,
    PingCollector,
    EbpfCollector,
]


def build_enterprise_collectors(settings: "Settings", runner: "SubprocessRunner") -> list["BaseCollector"]:
    """Instantiate all enterprise collector plugins."""
    return [cls(settings, runner) for cls in ENTERPRISE_COLLECTOR_CLASSES]
