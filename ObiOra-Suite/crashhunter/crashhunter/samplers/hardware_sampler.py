"""Hardware sensors: IPMI, temperature, SMART, PCI, EDAC, NVMe, RAID."""

from __future__ import annotations

from typing import Any

from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner


class HardwareSampler(BaseSampler):
    name = "hardware"

    def __init__(self, runner: SubprocessRunner) -> None:
        self.runner = runner

    def sample(self) -> dict[str, Any]:
        return {
            "ipmi_sensors": self.runner.run_text(["ipmitool", "sensor", "list"]),
            "ipmi_sel": self.runner.run_text(["ipmitool", "sel", "list", "last", "10"]),
            "temperature": self.runner.run_text(["sensors", "-j"]),
            "fans": self.runner.run_text(["sensors"]),
            "power": self.runner.run_text(["ipmitool", "dcmi", "power", "reading"]),
            "pci_errors": self.runner.run_text(["bash", "-c", "dmesg | grep -iE 'pci|aer' | tail -15"]),
            "edac": ProcReader.edac_errors(),
            "nvme_smart": self.runner.run_text(
                ["bash", "-c", "for n in /dev/nvme*n*; do [ -b \"$n\" ] && nvme smart-log \"$n\" 2>/dev/null; done"]
            ),
            "raid": self.runner.run_text(["cat", "/proc/mdstat"]),
            "usb": self.runner.run_text(["lsusb"]),
            "lvm": self.runner.run_text(["lvs", "--noheadings", "-o", "lv_name,vg_name,lv_size,lv_attr"]),
            "io_wait_note": "see cpu iowait in /proc/stat via cpu sampler",
        }
