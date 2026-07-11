"""Enterprise hardware plugins: IPMI, SMART, RAID, temperature, PCI."""

from __future__ import annotations

from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector
from crashhunter.utils.proc import ProcReader


class IpmiCollector(TimedCollector):
    name = "ipmi"
    priority = 140

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "sensors": self.timed_command("ipmi_sensors", ["ipmitool", "sensor", "list"]),
            "sel": self.timed_command("ipmi_sel", ["ipmitool", "sel", "list", "last", "20"]),
            "power": self.timed_command("ipmi_power", ["ipmitool", "dcmi", "power", "reading"]),
            "bmc_info": self.timed_command("ipmi_bmc", ["ipmitool", "bmc", "info"]),
        }


class SmartCollector(TimedCollector):
    name = "smart"
    priority = 145

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "scsi": self.timed_command(
                "smartctl_scsi",
                ["bash", "-c", "for d in /dev/sd? /dev/nvme?n?; do [ -b \"$d\" ] && smartctl -H -A \"$d\" 2>/dev/null; done"],
            ),
            "nvme": self.timed_command(
                "nvme_smart",
                ["bash", "-c", "for n in /dev/nvme*n*; do [ -b \"$n\" ] && nvme smart-log \"$n\" 2>/dev/null; done"],
            ),
        }


class RaidCollector(TimedCollector):
    name = "raid"
    priority = 150

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "mdstat": self.read_proc("/proc/mdstat"),
            "megacli": self.timed_command("megacli", ["bash", "-c", "which megacli && megacli -LDInfo -Lall -aALL 2>/dev/null"]),
        }


class TemperatureCollector(TimedCollector):
    name = "temperature"
    priority = 155

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "sensors": self.timed_command("sensors", ["sensors"]),
            "sensors_json": self.timed_command("sensors_j", ["sensors", "-j"]),
            "thermal_zone": self.timed_command(
                "thermal",
                ["bash", "-c", "for z in /sys/class/thermal/thermal_zone*/temp; do echo \"$z: $(cat $z 2>/dev/null)\"; done"],
            ),
        }


class PciCollector(TimedCollector):
    name = "pci"
    priority = 160

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "lspci": self.timed_command("lspci", ["lspci", "-vv"]),
            "aer": self.timed_command("pci_aer", ["bash", "-c", "dmesg | grep -iE 'pci|aer' | tail -20"]),
            "mce": self.timed_command("mce", ["bash", "-c", "dmesg | grep -iE 'mce|machine check' | tail -15"]),
            "edac": ProcReader.edac_errors(),
        }
