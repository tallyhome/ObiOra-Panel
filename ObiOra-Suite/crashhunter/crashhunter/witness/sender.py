"""Witness heartbeat sender — runs on the dedicated server."""

from __future__ import annotations

import json
import logging
import urllib.error
import urllib.request
from typing import Any

from crashhunter.config.settings import Settings

logger = logging.getLogger("crashhunter.witness.sender")


class WitnessSender:
    """Send heartbeat payloads to the remote witness (VPS) every cycle."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self._last_status = "unknown"

    def build_payload(self, snapshot: dict[str, Any], incident_mode: bool = False) -> dict[str, Any]:
        system = snapshot.get("system", {})
        cpu = snapshot.get("cpu", {})
        memory = snapshot.get("memory", {})
        disk = snapshot.get("disk", {})
        virtualizor = snapshot.get("virtualizor", {})
        hardware = snapshot.get("hardware", {})
        ping = snapshot.get("ping", {})
        pressure = snapshot.get("pressure", {})

        vm_count = 0
        if isinstance(virtualizor, dict):
            vm_list = virtualizor.get("virsh_list", "")
            if isinstance(vm_list, str):
                vm_count = len([l for l in vm_list.splitlines() if l.strip() and "Id" not in l])

        temp_c: float | None = None
        if isinstance(hardware, dict):
            temps = hardware.get("temperatures", {})
            if isinstance(temps, dict):
                for val in temps.values():
                    if isinstance(val, (int, float)):
                        temp_c = float(val)
                        break

        return {
            "host": self.settings.witness.host_id or self.settings.hostname,
            "timestamp": snapshot.get("timestamp_us", ""),
            "uptime_seconds": system.get("uptime_seconds"),
            "load_1": system.get("load_1"),
            "load_5": system.get("load_5"),
            "load_15": system.get("load_15"),
            "cpu_percent": cpu.get("total_percent"),
            "iowait_percent": cpu.get("iowait_percent"),
            "memory_used_percent": memory.get("used_percent"),
            "memory_available_mb": memory.get("available_mb"),
            "disk_io_wait": disk.get("iowait_percent"),
            "vm_count": vm_count,
            "virtualizor_ok": not virtualizor.get("error") if isinstance(virtualizor, dict) else None,
            "temperature_c": temp_c,
            "ping_ms": ping.get("external_ms") if isinstance(ping, dict) else None,
            "pressure_cpu_avg10": _pressure_avg10(pressure, "cpu"),
            "pressure_io_avg10": _pressure_avg10(pressure, "io"),
            "pressure_memory_avg10": _pressure_avg10(pressure, "memory"),
            "crashhunter_status": "incident" if incident_mode else "running",
            "incident_mode": incident_mode,
            "boot_id": system.get("boot_id"),
            "ring_count": snapshot.get("ring_count"),
        }

    def send(self, snapshot: dict[str, Any], incident_mode: bool = False) -> bool:
        if not self.settings.witness.enabled:
            return False
        url = self.settings.witness.receiver_url.rstrip("/") + "/api/v1/witness/heartbeat"
        payload = self.build_payload(snapshot, incident_mode)
        data = json.dumps(payload).encode("utf-8")
        headers = {"Content-Type": "application/json"}
        token = self.settings.witness.token
        if token:
            headers["Authorization"] = f"Bearer {token}"
        req = urllib.request.Request(url, data=data, headers=headers, method="POST")
        try:
            with urllib.request.urlopen(req, timeout=self.settings.witness.send_timeout_seconds) as resp:
                self._last_status = "ok" if resp.status == 200 else f"http_{resp.status}"
                return resp.status == 200
        except urllib.error.URLError as exc:
            self._last_status = f"error:{exc.reason}"
            logger.debug("Witness heartbeat failed: %s", exc)
            return False

    @property
    def last_status(self) -> str:
        return self._last_status


def _pressure_avg10(pressure: Any, resource: str) -> float | None:
    if not isinstance(pressure, dict):
        return None
    block = pressure.get(resource, {})
    if isinstance(block, dict):
        return block.get("avg10")
    return None
