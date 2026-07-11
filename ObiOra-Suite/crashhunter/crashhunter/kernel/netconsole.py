"""Netconsole — stream kernel panics to remote VPS."""

from __future__ import annotations

import logging
import shutil
from pathlib import Path
from typing import Any

from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.netconsole")

MODPROBE_CONF = Path("/etc/modprobe.d/crashhunter-netconsole.conf")


class NetconsoleManager:
    """Configure netconsole to forward kernel logs to witness VPS."""

    def __init__(self, settings: Any) -> None:
        self.settings = settings.netconsole
        self.runner = SubprocessRunner(default_timeout=5.0)

    def is_configured(self) -> bool:
        return MODPROBE_CONF.exists()

    def configure(self) -> dict[str, Any]:
        if not self.settings.enabled:
            return {"configured": False, "reason": "disabled"}
        if not self.settings.remote_ip or not self.settings.local_ip:
            return {"configured": False, "reason": "missing_ip_config"}

        local_port = self.settings.local_port
        remote_port = self.settings.remote_port
        remote_ip = self.settings.remote_ip
        local_ip = self.settings.local_ip
        dev = self.settings.interface

        # netconsole=@/local_port,local_ip/remote_port,remote_ip,dev
        line = (
            f"options netconsole netconsole=@{local_port},{local_ip}/"
            f"{remote_port},{remote_ip},{dev}"
        )
        try:
            MODPROBE_CONF.write_text(line + "\n", encoding="utf-8")
        except OSError as exc:
            return {"configured": False, "reason": str(exc)}

        if shutil.which("modprobe"):
            result = self.runner.run(["modprobe", "netconsole"], timeout=5.0)
            return {
                "configured": True,
                "modprobe": result.returncode == 0,
                "config": line,
            }
        return {"configured": True, "modprobe": False, "config": line}

    def status(self) -> dict[str, Any]:
        loaded = Path("/sys/module/netconsole").exists()
        return {
            "enabled": self.settings.enabled,
            "loaded": loaded,
            "configured": self.is_configured(),
            "remote_ip": self.settings.remote_ip,
            "local_ip": self.settings.local_ip,
        }

    def remove(self) -> dict[str, Any]:
        MODPROBE_CONF.unlink(missing_ok=True)
        if Path("/sys/module/netconsole").exists() and shutil.which("modprobe"):
            self.runner.run(["modprobe", "-r", "netconsole"], timeout=5.0)
        return {"removed": True}
