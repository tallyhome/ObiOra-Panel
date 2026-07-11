"""Safe /proc and sysfs readers."""

from __future__ import annotations

import os
import re
from pathlib import Path
from typing import Any


class ProcReader:
    """Lightweight reader for /proc and sysfs without external dependencies."""

    @staticmethod
    def read_text(path: str | Path, default: str = "") -> str:
        try:
            return Path(path).read_text(encoding="utf-8", errors="replace").strip()
        except OSError:
            return default

    @staticmethod
    def read_lines(path: str | Path) -> list[str]:
        try:
            return Path(path).read_text(encoding="utf-8", errors="replace").splitlines()
        except OSError:
            return []

    @staticmethod
    def read_key_values(path: str | Path) -> dict[str, str]:
        result: dict[str, str] = {}
        for line in ProcReader.read_lines(path):
            if ":" in line:
                key, value = line.split(":", 1)
                result[key.strip()] = value.strip()
        return result

    @staticmethod
    def boot_id() -> str:
        return ProcReader.read_text("/proc/sys/kernel/random/boot_id")

    @staticmethod
    def uptime() -> tuple[float, float]:
        parts = ProcReader.read_text("/proc/uptime", "0 0").split()
        if len(parts) >= 2:
            return float(parts[0]), float(parts[1])
        return 0.0, 0.0

    @staticmethod
    def loadavg() -> dict[str, float]:
        parts = ProcReader.read_text("/proc/loadavg", "0 0 0").split()
        result: dict[str, float] = {}
        if len(parts) >= 3:
            result["load_1"] = float(parts[0])
            result["load_5"] = float(parts[1])
            result["load_15"] = float(parts[2])
        if len(parts) >= 5:
            match = re.match(r"(\d+)/(\d+)", parts[3])
            if match:
                result["running"] = float(match.group(1))
                result["total"] = float(match.group(2))
            result["last_pid"] = float(parts[4])
        return result

    @staticmethod
    def meminfo() -> dict[str, int]:
        data: dict[str, int] = {}
        for line in ProcReader.read_lines("/proc/meminfo"):
            parts = line.split()
            if len(parts) >= 2 and parts[1].isdigit():
                data[parts[0].rstrip(":")] = int(parts[1])
        return data

    @staticmethod
    def vmstat() -> dict[str, int]:
        data: dict[str, int] = {}
        for line in ProcReader.read_lines("/proc/vmstat"):
            parts = line.split()
            if len(parts) == 2 and parts[1].lstrip("-").isdigit():
                data[parts[0]] = int(parts[1])
        return data

    @staticmethod
    def stat() -> dict[str, int]:
        text = ProcReader.read_text("/proc/stat")
        data: dict[str, int] = {}
        for line in text.splitlines():
            parts = line.split()
            if not parts:
                continue
            key = parts[0].rstrip(":")
            if key.startswith("cpu"):
                data[key] = sum(int(p) for p in parts[1:] if p.isdigit())
            elif parts[0] == "ctxt":
                data["ctxt"] = int(parts[1])
            elif parts[0] == "btime":
                data["btime"] = int(parts[1])
            elif parts[0] == "processes":
                data["processes"] = int(parts[1])
            elif parts[0] == "procs_running":
                data["procs_running"] = int(parts[1])
            elif parts[0] == "procs_blocked":
                data["procs_blocked"] = int(parts[1])
        return data

    @staticmethod
    def interrupts() -> dict[str, int]:
        total = 0
        for line in ProcReader.read_lines("/proc/interrupts")[1:]:
            parts = line.split()
            if len(parts) >= 2 and parts[-1].isdigit():
                total += int(parts[-1])
            elif len(parts) >= 2:
                for part in reversed(parts):
                    if part.isdigit():
                        total += int(part)
                        break
        return {"total": total}

    @staticmethod
    def softirqs() -> dict[str, int]:
        totals: dict[str, int] = {}
        for line in ProcReader.read_lines("/proc/softirqs"):
            parts = line.split()
            if not parts:
                continue
            name = parts[0].rstrip(":")
            totals[name] = sum(int(p) for p in parts[1:] if p.isdigit())
        return totals

    @staticmethod
    def parse_pressure_text(text: str) -> dict[str, dict[str, float | dict[str, float]]]:
        """Parse /proc/pressure/* content into normalized some/full blocks."""
        result: dict[str, dict[str, float | dict[str, float]]] = {}
        for line in text.splitlines():
            line = line.strip()
            if not line:
                continue
            match = re.match(
                r"^(some|full)\s+avg10=([\d.]+)\s+avg60=([\d.]+)\s+avg300=([\d.]+)\s+total=(\d+)",
                line,
            )
            if not match:
                continue
            kind, avg10, avg60, avg300, total = match.groups()
            block = {
                "avg10": float(avg10),
                "avg60": float(avg60),
                "avg300": float(avg300),
                "total": int(total),
            }
            # Single-line files are keyed as "some" by resource name later.
            result[kind] = block
        return result

    @staticmethod
    def pressure() -> dict[str, dict[str, Any]]:
        result: dict[str, dict[str, Any]] = {}
        for resource in ("cpu", "memory", "io"):
            path = f"/proc/pressure/{resource}"
            raw = ProcReader.read_text(path)
            if not raw:
                continue
            parsed_lines = ProcReader.parse_pressure_text(raw)
            if not parsed_lines:
                continue
            some = parsed_lines.get("some")
            full = parsed_lines.get("full")
            entry: dict[str, Any] = {}
            if isinstance(some, dict):
                entry["some"] = some
                entry["some_avg10"] = some["avg10"]
                entry["some_avg60"] = some["avg60"]
                entry["some_avg300"] = some["avg300"]
                entry["avg10"] = some["avg10"]
            if isinstance(full, dict):
                entry["full"] = full
                entry["full_avg10"] = full["avg10"]
            if entry:
                result[resource] = entry
        return result

    @staticmethod
    def pressure_detailed() -> dict[str, Any]:
        """Structured PSI read with availability and per-resource errors."""
        out: dict[str, Any] = {"available": False, "psi": {}, "parsed": {}, "errors": {}}
        any_data = False
        for resource in ("cpu", "memory", "io"):
            path = f"/proc/pressure/{resource}"
            raw = ProcReader.read_text(path)
            if not raw:
                out["errors"][resource] = "missing_or_empty"
                out["psi"][resource] = ""
                continue
            out["psi"][resource] = raw
            parsed = ProcReader.parse_pressure_text(raw)
            if not parsed:
                out["errors"][resource] = "parse_failed"
                continue
            normalized: dict[str, Any] = {}
            some = parsed.get("some")
            full = parsed.get("full")
            if isinstance(some, dict):
                normalized["some"] = some
                normalized["avg10"] = some["avg10"]
            if isinstance(full, dict):
                normalized["full"] = full
            out["parsed"][resource] = normalized
            any_data = True
        out["available"] = any_data
        return out

    @staticmethod
    def kernel_version() -> str:
        return ProcReader.read_text("/proc/version")

    @staticmethod
    def kernel_taint() -> int:
        text = ProcReader.read_text("/proc/sys/kernel/tainted", "0")
        try:
            return int(text)
        except ValueError:
            return 0

    @staticmethod
    def list_pids() -> list[int]:
        pids: list[int] = []
        proc = Path("/proc")
        if not proc.is_dir():
            return pids
        for entry in proc.iterdir():
            if entry.name.isdigit():
                pids.append(int(entry.name))
        return pids

    @staticmethod
    def pid_stat(pid: int) -> dict[str, Any]:
        text = ProcReader.read_text(f"/proc/{pid}/stat")
        if not text:
            return {}
        match = re.match(
            r"(\d+) \((.+)\) (\S) (\d+).*?"
            r"(\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) "
            r"(\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) "
            r"(\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) (\d+) "
            r"(\d+) (\d+) (\d+)",
            text,
        )
        if not match:
            return {"pid": pid}
        groups = match.groups()
        utime = int(groups[11])
        stime = int(groups[12])
        rss_pages = int(groups[21])
        return {
            "pid": pid,
            "comm": groups[1],
            "state": groups[2],
            "ppid": int(groups[3]),
            "utime": utime,
            "stime": stime,
            "rss_kb": rss_pages * (os.sysconf("SC_PAGE_SIZE") // 1024),
            "threads": int(groups[17]),
        }

    @staticmethod
    def pid_status(pid: int) -> dict[str, str]:
        return ProcReader.read_key_values(f"/proc/{pid}/status")

    @staticmethod
    def pid_io(pid: int) -> dict[str, int]:
        data: dict[str, int] = {}
        for line in ProcReader.read_lines(f"/proc/{pid}/io"):
            parts = line.split()
            if len(parts) == 2 and parts[1].isdigit():
                data[parts[0].rstrip(":")] = int(parts[1])
        return data

    @staticmethod
    def open_files_count(pid: int) -> int:
        fd_dir = Path(f"/proc/{pid}/fd")
        try:
            return sum(1 for _ in fd_dir.iterdir())
        except OSError:
            return 0

    @staticmethod
    def mounts() -> list[dict[str, str]]:
        mounts: list[dict[str, str]] = []
        for line in ProcReader.read_lines("/proc/mounts"):
            parts = line.split()
            if len(parts) >= 3:
                mounts.append(
                    {"device": parts[0], "mount": parts[1], "fstype": parts[2]}
                )
        return mounts

    @staticmethod
    def diskstats() -> list[dict[str, Any]]:
        devices: list[dict[str, Any]] = []
        for line in ProcReader.read_lines("/proc/diskstats"):
            parts = line.split()
            if len(parts) < 14:
                continue
            devices.append(
                {
                    "device": parts[2],
                    "reads": int(parts[3]),
                    "reads_merged": int(parts[4]),
                    "sectors_read": int(parts[5]),
                    "read_ms": int(parts[6]),
                    "writes": int(parts[7]),
                    "writes_merged": int(parts[8]),
                    "sectors_written": int(parts[9]),
                    "write_ms": int(parts[10]),
                    "in_flight": int(parts[11]),
                    "io_ms": int(parts[12]),
                    "weighted_io_ms": int(parts[13]),
                }
            )
        return devices

    @staticmethod
    def netdev() -> list[dict[str, Any]]:
        interfaces: list[dict[str, Any]] = []
        lines = ProcReader.read_lines("/proc/net/dev")
        for line in lines[2:]:
            if ":" not in line:
                continue
            name, stats = line.split(":", 1)
            parts = stats.split()
            if len(parts) < 16:
                continue
            interfaces.append(
                {
                    "iface": name.strip(),
                    "rx_bytes": int(parts[0]),
                    "rx_packets": int(parts[1]),
                    "rx_errs": int(parts[2]),
                    "rx_drop": int(parts[3]),
                    "tx_bytes": int(parts[8]),
                    "tx_packets": int(parts[9]),
                    "tx_errs": int(parts[10]),
                    "tx_drop": int(parts[11]),
                }
            )
        return interfaces

    @staticmethod
    def tcp_sockets() -> list[dict[str, str]]:
        return ProcReader._parse_proc_net("/proc/net/tcp", "tcp")

    @staticmethod
    def udp_sockets() -> list[dict[str, str]]:
        return ProcReader._parse_proc_net("/proc/net/udp", "udp")

    @staticmethod
    def _parse_proc_net(path: str, proto: str) -> list[dict[str, str]]:
        sockets: list[dict[str, str]] = []
        lines = ProcReader.read_lines(path)
        for line in lines[1:]:
            parts = line.split()
            if len(parts) < 10:
                continue
            state_hex = parts[3]
            state = ProcReader._tcp_state(state_hex)
            sockets.append(
                {
                    "proto": proto,
                    "local": parts[1],
                    "remote": parts[2],
                    "state": state,
                    "uid": parts[7],
                    "inode": parts[9],
                }
            )
        return sockets

    @staticmethod
    def _tcp_state(hex_state: str) -> str:
        states = {
            "01": "ESTABLISHED",
            "02": "SYN_SENT",
            "03": "SYN_RECV",
            "04": "FIN_WAIT1",
            "05": "FIN_WAIT2",
            "06": "TIME_WAIT",
            "07": "CLOSE",
            "08": "CLOSE_WAIT",
            "09": "LAST_ACK",
            "0A": "LISTEN",
            "0B": "CLOSING",
        }
        return states.get(hex_state.upper(), hex_state)

    @staticmethod
    def modules() -> list[str]:
        return [
            line.split()[0]
            for line in ProcReader.read_lines("/proc/modules")
            if line.strip()
        ]

    @staticmethod
    def mce_log() -> list[str]:
        return ProcReader.read_lines("/sys/devices/system/machinecheck/machinecheck0/trigger")

    @staticmethod
    def edac_errors() -> dict[str, str]:
        result: dict[str, str] = {}
        edac = Path("/sys/devices/system/edac")
        if not edac.exists():
            return result
        for mc in edac.glob("mc/mc*"):
            for ce_file in mc.glob("ce_count"):
                result[str(ce_file)] = ProcReader.read_text(ce_file)
            for ue_file in mc.glob("ue_count"):
                result[str(ue_file)] = ProcReader.read_text(ue_file)
        return result
