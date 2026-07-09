"""Collecteurs de métriques système."""

from __future__ import annotations

import glob
import os
import re
import time
from abc import ABC, abstractmethod
from typing import Any

from crash_analyzer.boot_journal import collect_boot_journal_snapshot, persistent_journal_enabled
from crash_analyzer.util import run_cmd


def read_file(path: str, default: str = "") -> str:
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            return fh.read().strip()
    except OSError:
        return default


def read_int(path: str, default: int = 0) -> int:
    try:
        return int(read_file(path, str(default)))
    except ValueError:
        return default


class BaseCollector(ABC):
    """Collecteur de métriques avec interface commune."""

    name: str = "base"

    @abstractmethod
    def collect(self) -> dict[str, Any]:
        """Collecte les métriques du module."""
        ...


class CpuCollector(BaseCollector):
    name = "cpu"

    def __init__(self) -> None:
        self._prev_idle = 0
        self._prev_total = 0

    def collect(self) -> dict[str, Any]:
        line = read_file("/proc/stat").splitlines()[0]
        parts = [int(x) for x in line.split()[1:]]
        idle = parts[3] + (parts[4] if len(parts) > 4 else 0)
        total = sum(parts)
        delta_total = total - self._prev_total
        delta_idle = idle - self._prev_idle
        usage = 0.0
        if delta_total > 0:
            usage = round(100.0 * (1.0 - delta_idle / delta_total), 2)
        self._prev_idle, self._prev_total = idle, total
        load = read_file("/proc/loadavg", "0 0 0").split()[:3]
        return {
            "usage_percent": usage,
            "load_1": float(load[0]) if load else 0.0,
            "load_5": float(load[1]) if len(load) > 1 else 0.0,
            "load_15": float(load[2]) if len(load) > 2 else 0.0,
            "cores": os.cpu_count() or 1,
        }


class MemoryCollector(BaseCollector):
    name = "memory"

    def collect(self) -> dict[str, Any]:
        info: dict[str, int] = {}
        for line in read_file("/proc/meminfo").splitlines():
            if ":" not in line:
                continue
            key, val = line.split(":", 1)
            info[key.strip()] = int(val.strip().split()[0])
        total = info.get("MemTotal", 0)
        avail = info.get("MemAvailable", info.get("MemFree", 0))
        used = max(total - avail, 0)
        return {
            "total_kb": total,
            "available_kb": avail,
            "used_kb": used,
            "used_percent": round(100.0 * used / total, 2) if total else 0.0,
        }


class SwapCollector(BaseCollector):
    name = "swap"

    def collect(self) -> dict[str, Any]:
        info: dict[str, int] = {}
        for line in read_file("/proc/meminfo").splitlines():
            if ":" not in line:
                continue
            key, val = line.split(":", 1)
            info[key.strip()] = int(val.strip().split()[0])
        total = info.get("SwapTotal", 0)
        free = info.get("SwapFree", 0)
        used = max(total - free, 0)
        return {
            "total_kb": total,
            "used_kb": used,
            "used_percent": round(100.0 * used / total, 2) if total else 0.0,
        }


class PsiCollector(BaseCollector):
    name = "psi"

    def collect(self) -> dict[str, Any]:
        data: dict[str, Any] = {}
        for resource in ("cpu", "memory", "io"):
            content = read_file(f"/proc/pressure/{resource}")
            if not content:
                continue
            avg10 = re.search(r"avg10=([\d.]+)", content)
            data[resource] = {"avg10": float(avg10.group(1)) if avg10 else 0.0}
        return data


class DiskCollector(BaseCollector):
    name = "disk"

    def collect(self) -> dict[str, Any]:
        mounts = []
        iowait = 0.0
        stat_line = read_file("/proc/stat").splitlines()[0] if read_file("/proc/stat") else ""
        if stat_line.startswith("cpu "):
            parts = [int(x) for x in stat_line.split()[1:]]
            if len(parts) >= 6:
                total = sum(parts)
                iowait = round(100.0 * parts[4] / total, 2) if total else 0.0
        for line in run_cmd(["df", "-P", "-B1", "--exclude-type=tmpfs", "--exclude-type=devtmpfs"]).splitlines()[1:]:
            cols = line.split()
            if len(cols) < 6:
                continue
            mounts.append({
                "mount": cols[5],
                "size_bytes": int(cols[1]),
                "used_bytes": int(cols[2]),
                "used_percent": int(cols[4].rstrip("%")),
            })
        return {"io_wait_percent": iowait, "mounts": mounts[:20]}


class NetworkCollector(BaseCollector):
    name = "network"

    def __init__(self) -> None:
        self._prev_rx: dict[str, int] = {}
        self._prev_tx: dict[str, int] = {}

    def collect(self) -> dict[str, Any]:
        interfaces: list[dict[str, Any]] = []
        for line in read_file("/proc/net/dev").splitlines()[2:]:
            if ":" not in line:
                continue
            iface, stats = line.split(":", 1)
            iface = iface.strip()
            if iface == "lo":
                continue
            cols = stats.split()
            rx, tx = int(cols[0]), int(cols[8])
            prev_rx = self._prev_rx.get(iface, rx)
            prev_tx = self._prev_tx.get(iface, tx)
            self._prev_rx[iface] = rx
            self._prev_tx[iface] = tx
            interfaces.append({
                "iface": iface,
                "rx_bytes": rx,
                "tx_bytes": tx,
                "rx_delta": max(rx - prev_rx, 0),
                "tx_delta": max(tx - prev_tx, 0),
            })
        conns = len(run_cmd(["ss", "-tan"]).splitlines()) - 1
        return {"interfaces": interfaces, "tcp_connections": max(conns, 0)}


class ThermalCollector(BaseCollector):
    name = "thermal"

    def collect(self) -> dict[str, Any]:
        temps: list[dict[str, Any]] = []
        for path in sorted(glob.glob("/sys/class/thermal/thermal_zone*/temp")):
            zone = path.split("thermal_zone")[-1].split("/")[0]
            milli = read_int(path)
            if milli > 0:
                temps.append({"zone": zone, "celsius": round(milli / 1000.0, 1)})
        return {"sensors": temps}


class SmartCollector(BaseCollector):
    name = "smart"

    def collect(self) -> dict[str, Any]:
        output = run_cmd(["smartctl", "-A", "-j", "/dev/sda"], timeout=5)
        if output.startswith("{"):
            return {"raw": output[:4000]}
        devices = []
        for dev in glob.glob("/dev/sd?") + glob.glob("/dev/nvme?n?"):
            brief = run_cmd(["smartctl", "-H", dev], timeout=3)
            if brief:
                devices.append({"device": dev, "health": brief.splitlines()[-1][:120]})
        return {"devices": devices[:10]}


class EdacCollector(BaseCollector):
    name = "edac"

    def collect(self) -> dict[str, Any]:
        ce = read_int("/sys/devices/system/edac/mc/mc0/ce_count", -1)
        ue = read_int("/sys/devices/system/edac/mc/mc0/ue_count", -1)
        return {"ce_count": ce, "ue_count": ue, "available": ce >= 0 or ue >= 0}


class RasdaemonCollector(BaseCollector):
    name = "rasdaemon"

    def collect(self) -> dict[str, Any]:
        status = run_cmd(["systemctl", "is-active", "rasdaemon"])
        errors = run_cmd(["ras-mc-ctl", "--errors"], timeout=4)
        return {"service_active": status == "active", "errors": errors[:2000]}


class JournalCollector(BaseCollector):
    name = "journal"

    def collect(self) -> dict[str, Any]:
        critical = run_cmd(
            ["journalctl", "-p", "0..3", "-n", "5", "--no-pager", "-o", "short-iso"],
            timeout=4,
        )
        return {
            "recent_critical": critical[:3000],
            "persistent_journal": persistent_journal_enabled(),
        }


class JournalBootCollector(BaseCollector):
    """journalctl --list-boots et extrait du boot précédent (-b -1)."""

    name = "journal_boot"
    _CACHE_SECONDS = 300

    def __init__(self) -> None:
        self._cache: dict[str, Any] | None = None
        self._cache_at: float = 0.0

    def collect(self) -> dict[str, Any]:
        now = time.time()
        if self._cache is not None and now - self._cache_at < self._CACHE_SECONDS:
            return {**self._cache, "cached": True}
        payload = collect_boot_journal_snapshot()
        payload["cached"] = False
        self._cache = payload
        self._cache_at = now
        return payload


class HardwareCollector(BaseCollector):
    """Inventaire matériel (dmidecode, lscpu, lspci) — cache 1 h."""

    name = "hardware"
    _CACHE_SECONDS = 3600

    def __init__(self) -> None:
        self._cache: dict[str, Any] | None = None
        self._cache_at: float = 0.0

    def collect(self) -> dict[str, Any]:
        now = time.time()
        if self._cache is not None and now - self._cache_at < self._CACHE_SECONDS:
            return {**self._cache, "cached": True}

        payload: dict[str, Any] = {
            "cached": False,
            "dmidecode_system": run_cmd(["dmidecode", "-t", "system"], timeout=5)[:4000],
            "dmidecode_baseboard": run_cmd(["dmidecode", "-t", "baseboard"], timeout=5)[:4000],
            "dmidecode_bios": run_cmd(["dmidecode", "-t", "bios"], timeout=5)[:4000],
            "lscpu": run_cmd(["lscpu"], timeout=4)[:4000],
            "lspci_network_storage": run_cmd(
                ["sh", "-c", "lspci 2>/dev/null | grep -Ei 'ethernet|network|raid|sas|nvme' || true"],
                timeout=5,
            )[:4000],
        }
        payload["available"] = any(
            payload[k] for k in (
                "dmidecode_system", "dmidecode_baseboard", "dmidecode_bios", "lscpu", "lspci_network_storage",
            )
        )
        self._cache = payload
        self._cache_at = now
        return payload


class ToolsCollector(BaseCollector):
    """Vérifie la présence des outils de diagnostic (strace, time, etc.)."""

    name = "tools"

    def collect(self) -> dict[str, Any]:
        tools = ("strace", "time", "dmidecode", "lscpu", "lspci", "journalctl")
        status = {tool: bool(run_cmd(["which", tool], timeout=2)) for tool in tools}
        return {
            "installed": status,
            "all_required": status.get("journalctl", False) and status.get("time", False),
        }


class DmesgCollector(BaseCollector):
    name = "dmesg"

    def collect(self) -> dict[str, Any]:
        tail = run_cmd(["dmesg", "-T", "-l", "err,crit,alert,emerg"], timeout=3)
        return {"errors": tail[:3000]}


class VirtualizorCollector(BaseCollector):
    name = "virtualizor"

    def collect(self) -> dict[str, Any]:
        if not os.path.isdir("/usr/local/virtualizor"):
            return {"installed": False}
        status = run_cmd(["systemctl", "is-active", "virt"], timeout=2)
        vms = run_cmd(["virsh", "-c", "qemu:///system", "list", "--all"], timeout=4)
        return {"installed": True, "service": status, "vms": vms[:2000]}


class LibvirtCollector(BaseCollector):
    name = "libvirt"

    def collect(self) -> dict[str, Any]:
        if not run_cmd(["which", "virsh"]):
            return {"available": False}
        domains = run_cmd(["virsh", "list", "--all"], timeout=4)
        return {"available": True, "domains": domains[:2000]}


class DockerCollector(BaseCollector):
    name = "docker"

    def collect(self) -> dict[str, Any]:
        if not run_cmd(["which", "docker"]):
            return {"available": False}
        containers = run_cmd(["docker", "ps", "-a", "--format", "{{.Names}}|{{.Status}}"], timeout=4)
        return {
            "available": True,
            "running": len([l for l in containers.splitlines() if "Up" in l]),
            "total": len([l for l in containers.splitlines() if l.strip()]),
        }


class SystemdCollector(BaseCollector):
    name = "systemd"

    def collect(self) -> dict[str, Any]:
        failed = run_cmd(["systemctl", "--failed", "--no-legend", "--plain"], timeout=3)
        units = [u.split()[0] for u in failed.splitlines() if u.strip()]
        return {"failed_count": len(units), "failed_units": units[:15]}


class ProcessesCollector(BaseCollector):
    name = "processes"

    def collect(self) -> dict[str, Any]:
        total = read_int("/proc/sys/kernel/pid_max", 0)
        running = len([1 for _ in open("/proc", encoding="utf-8") if _.isdigit()]) if os.path.isdir("/proc") else 0
        top = run_cmd(["ps", "-eo", "pid,pcpu,pmem,comm", "--sort=-pcpu"], timeout=3)
        lines = top.splitlines()[1:6]
        return {"pid_max": total, "count": running, "top_cpu": lines}


class IrqCollector(BaseCollector):
    name = "irq"

    def collect(self) -> dict[str, Any]:
        softirq = read_file("/proc/softirqs").splitlines()[:5]
        return {"softirq_sample": softirq}


class SshCollector(BaseCollector):
    name = "ssh"

    def collect(self) -> dict[str, Any]:
        sessions = run_cmd(["who"], timeout=2)
        auth_log = ""
        for path in ("/var/log/auth.log", "/var/log/secure"):
            if os.path.isfile(path):
                auth_log = run_cmd(["tail", "-n", "3", path], timeout=2)
                break
        return {"sessions": sessions[:500], "recent_auth": auth_log[:500]}


class LoadCollector(BaseCollector):
    name = "load"

    def collect(self) -> dict[str, Any]:
        uptime = read_file("/proc/uptime", "0 0").split()
        boot_id = read_file("/proc/sys/kernel/random/boot_id")
        return {
            "uptime_seconds": float(uptime[0]) if uptime else 0.0,
            "boot_id": boot_id,
        }


COLLECTOR_REGISTRY: dict[str, type[BaseCollector]] = {
    c.name: c
    for c in (
        CpuCollector, MemoryCollector, SwapCollector, PsiCollector, DiskCollector,
        NetworkCollector, ThermalCollector, SmartCollector, EdacCollector,
        RasdaemonCollector, JournalCollector, JournalBootCollector, HardwareCollector,
        ToolsCollector, DmesgCollector, VirtualizorCollector,
        LibvirtCollector, DockerCollector, SystemdCollector, ProcessesCollector,
        IrqCollector, SshCollector, LoadCollector,
    )
}


def build_collectors(enabled: list[str]) -> list[BaseCollector]:
    """Instancie les collecteurs activés."""
    collectors: list[BaseCollector] = []
    for name in enabled:
        cls = COLLECTOR_REGISTRY.get(name)
        if cls:
            collectors.append(cls())
    return collectors
