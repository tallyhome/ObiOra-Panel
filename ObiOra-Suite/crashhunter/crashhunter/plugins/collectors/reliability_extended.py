"""pstore, EDAC/MCE/rasdaemon, IPMI flight recorder, VM external heartbeat."""

from __future__ import annotations

import hashlib
import json
import logging
from pathlib import Path
from typing import Any

from crashhunter.kernel.pstore import read_pstore_at_boot
from crashhunter.plugins.collectors.timed_base import TimedCollector
from crashhunter.utils.proc import ProcReader

logger = logging.getLogger("crashhunter.collectors.reliability")

IPMI_STATE_FILE = "ipmi_sel_baseline.json"


class PstoreCollector(TimedCollector):
    name = "pstore"
    priority = 5

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {**self.collect_meta(), **read_pstore_at_boot()}


class EdacMceCollector(TimedCollector):
    name = "edac_mce"
    priority = 135

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "edac_sysfs": ProcReader.edac_errors(),
            "mcelog": self.timed_command("mcelog", ["bash", "-c", "which mcelog && mcelog --client 2>/dev/null | tail -30"]),
            "rasdaemon": self.timed_command(
                "rasdaemon",
                ["bash", "-c", "which ras-mc-ctl && ras-mc-ctl --errors 2>/dev/null; which rasdaemon && rasdaemon --status 2>/dev/null"],
            ),
            "edac_util": self.timed_command("edac_util", ["bash", "-c", "which edac-util && edac-util -v 2>/dev/null"]),
            "dmesg_mce": self.timed_command(
                "dmesg_mce",
                ["bash", "-c", "dmesg -T 2>/dev/null | grep -iE 'mce|machine check|edac|aer|pcie.*error|corrected error|uncorrected error' | tail -40"],
            ),
            "aer_sysfs": self._read_aer_sysfs(),
        }

    @staticmethod
    def _read_aer_sysfs() -> dict[str, str]:
        out: dict[str, str] = {}
        root = Path("/sys/bus/pci/devices")
        if not root.is_dir():
            return out
        for dev in list(root.iterdir())[:40]:
            aer = dev / "aer_dev_correctable"
            if aer.is_file():
                try:
                    out[str(dev.name)] = aer.read_text(encoding="utf-8", errors="replace").strip()[:200]
                except OSError:
                    continue
        return out


class IpmiFlightRecorderCollector(TimedCollector):
    """Full BMC flight recorder with SEL differential after reboot."""

    name = "ipmi_flight"
    priority = 138

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        state_path = self.settings.base_dir / "data" / IPMI_STATE_FILE
        sel_raw = self.timed_command("ipmi_sel_full", ["ipmitool", "sel", "list"])
        sdr = self.timed_command("ipmi_sdr", ["ipmitool", "sdr", "type", "Temperature"])
        fans = self.timed_command("ipmi_fans", ["ipmitool", "sdr", "type", "Fan"])
        voltage = self.timed_command("ipmi_voltage", ["ipmitool", "sdr", "type", "Voltage"])
        psu = self.timed_command("ipmi_psu", ["bash", "-c", "ipmitool sdr type 'Power Supply' 2>/dev/null; ipmitool dcmi power reading 2>/dev/null"])
        chassis = self.timed_command("ipmi_chassis", ["ipmitool", "chassis", "status"])
        sensors = self.timed_command("ipmi_sensors", ["ipmitool", "sensor", "list"])

        sel_text = _cmd_stdout(sel_raw)
        sel_hash = hashlib.sha256(sel_text.encode("utf-8", errors="replace")).hexdigest()[:16]
        previous = _load_json(state_path)
        diff = _sel_diff(previous.get("sel_text", ""), sel_text)

        _save_json(state_path, {"sel_text": sel_text, "sel_hash": sel_hash})

        return {
            **self.collect_meta(),
            "sel": sel_raw,
            "sel_hash": sel_hash,
            "sel_diff": diff,
            "sdr_temperature": sdr,
            "sdr_fans": fans,
            "sdr_voltage": voltage,
            "psu": psu,
            "chassis_status": chassis,
            "sensors": sensors,
            "bmc_info": self.timed_command("ipmi_bmc", ["ipmitool", "bmc", "info"]),
        }


class VmHeartbeatCollector(TimedCollector):
    """External VPS liveness: QEMU running ≠ OS alive."""

    name = "vm_heartbeat"
    priority = 88

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        vms = self._list_vms()
        probes: list[dict[str, Any]] = []

        for vm in vms[:20]:
            name = vm.get("name", "")
            ip = vm.get("ip", "") or self._resolve_vm_ip(name)
            state = vm.get("state", "")
            probe: dict[str, Any] = {
                "name": name,
                "ip": ip,
                "virsh_state": state,
                "HOST_ALIVE": True,
                "VM_QEMU_ALIVE": state in ("running", "paused"),
                "VM_NETWORK_ALIVE": None,
                "VM_OS_ALIVE": None,
                "VM_SERVICE_ALIVE": None,
            }
            if ip and state == "running":
                ping = self.timed_command(f"ping_{ip}", ["ping", "-c", "1", "-W", "2", ip], timeout=3.0)
                tcp22 = self.timed_command(
                    f"tcp_{ip}_22",
                    ["bash", "-c", f"timeout 2 bash -c '</dev/tcp/{ip}/22' 2>/dev/null"],
                    timeout=3.0,
                )
                tcp443 = self.timed_command(
                    f"tcp_{ip}_443",
                    ["bash", "-c", f"timeout 2 bash -c '</dev/tcp/{ip}/443' 2>/dev/null || timeout 2 bash -c '</dev/tcp/{ip}/80' 2>/dev/null"],
                    timeout=3.0,
                )
                probe["VM_NETWORK_ALIVE"] = ping.get("returncode") == 0
                probe["VM_OS_ALIVE"] = tcp22.get("returncode") == 0
                probe["VM_SERVICE_ALIVE"] = tcp443.get("returncode") == 0
            probes.append(probe)

        return {**self.collect_meta(), "vms": probes, "summary": _summarize_vm_probes(probes)}

    def _list_vms(self) -> list[dict[str, str]]:
        result = self.timed_command("virsh_list", ["virsh", "list", "--all"])
        text = _cmd_stdout(result)
        vms: list[dict[str, str]] = []
        for line in text.splitlines()[2:]:
            parts = line.split()
            if len(parts) >= 3:
                vms.append({"id": parts[0], "name": parts[1], "state": parts[2]})
        return vms

    def _resolve_vm_ip(self, name: str) -> str:
        if not name:
            return ""
        result = self.timed_command(
            f"domifaddr_{name}",
            ["virsh", "domifaddr", name, "--source", "lease"],
            timeout=4.0,
        )
        text = _cmd_stdout(result)
        for line in text.splitlines():
            parts = line.split()
            if len(parts) >= 4 and "/" in parts[3]:
                return parts[3].split("/")[0]
        result2 = self.timed_command(
            f"domifaddr_{name}_agent",
            ["virsh", "domifaddr", name, "--source", "agent"],
            timeout=4.0,
        )
        for line in _cmd_stdout(result2).splitlines():
            parts = line.split()
            if len(parts) >= 4 and "/" in parts[3]:
                return parts[3].split("/")[0]
        return ""


def _cmd_stdout(result: Any) -> str:
    if isinstance(result, dict):
        return str(result.get("stdout", result.get("output", "")))
    return str(getattr(result, "stdout", result))


def _load_json(path: Path) -> dict[str, Any]:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {}


def _save_json(path: Path, data: dict[str, Any]) -> None:
    try:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(data, indent=2), encoding="utf-8")
    except OSError as exc:
        logger.debug("IPMI state save failed: %s", exc)


def _sel_diff(previous: str, current: str) -> dict[str, Any]:
    prev_lines = {ln.strip() for ln in previous.splitlines() if ln.strip()}
    curr_lines = [ln.strip() for ln in current.splitlines() if ln.strip()]
    new_lines = [ln for ln in curr_lines if ln not in prev_lines]
    return {
        "new_events_count": len(new_lines),
        "new_events": new_lines[:30],
        "has_new_since_baseline": len(new_lines) > 0,
    }


def _summarize_vm_probes(probes: list[dict[str, Any]]) -> dict[str, int]:
    counts = {
        "qemu_running": 0,
        "os_dead_qemu_running": 0,
        "network_dead": 0,
        "service_dead": 0,
    }
    for p in probes:
        if p.get("VM_QEMU_ALIVE"):
            counts["qemu_running"] += 1
            if p.get("VM_OS_ALIVE") is False:
                counts["os_dead_qemu_running"] += 1
            if p.get("VM_NETWORK_ALIVE") is False:
                counts["network_dead"] += 1
            if p.get("VM_SERVICE_ALIVE") is False:
                counts["service_dead"] += 1
    return counts
