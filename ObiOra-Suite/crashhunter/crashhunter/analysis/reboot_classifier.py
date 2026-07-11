"""Reboot type classifier — soft, hard, IPMI, OVH, panic, watchdog, etc."""

from __future__ import annotations

import logging
import re
from typing import Any

from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.reboot_classifier")


class RebootClassifier:
    """Classify reboot type after comparing boot_id, uptime, journals, IPMI SEL."""

    def classify(self, reboot_info: dict[str, object]) -> dict[str, Any]:
        runner = SubprocessRunner(default_timeout=3.0)
        journal_boot = runner.run_text(["journalctl", "-b", "-1", "-n", "50", "--no-pager"])
        journal_cur = runner.run_text(["journalctl", "-b", "0", "-n", "30", "--no-pager"])
        ipmi_sel = runner.run_text(["ipmitool", "sel", "list", "last", "15"])
        dmesg_boot = runner.run_text(["dmesg", "-T"])

        reboot_type = "unknown"
        confidence = 0.5
        evidence: list[str] = []

        corpus = f"{journal_boot}\n{journal_cur}\n{ipmi_sel}\n{dmesg_boot}".lower()

        if reboot_info.get("reason") == "boot_id_changed":
            evidence.append("boot_id changed since last snapshot")

        if re.search(r"kernel panic|panic - not syncing", corpus):
            reboot_type = "kernel_panic"
            confidence = 0.95
            evidence.append("kernel panic in logs")
        elif re.search(r"watchdog.*(reset|reboot|bite)", corpus):
            reboot_type = "watchdog"
            confidence = 0.92
            evidence.append("watchdog reset detected")
        elif re.search(r"hard reset|power cycle|chassis power", corpus):
            reboot_type = "hard_reboot_ipmi"
            confidence = 0.88
            evidence.append("IPMI hard reset / power cycle")
        elif re.search(r"ovh|softerr|reboot requested", corpus):
            reboot_type = "ovh_reboot"
            confidence = 0.85
            evidence.append("OVH-initiated reboot markers")
        elif re.search(r"power loss|ac lost|psu", corpus):
            reboot_type = "power_loss"
            confidence = 0.83
            evidence.append("power loss event")
        elif reboot_info.get("reason") == "boot_id_changed":
            prev_uptime = reboot_info.get("previous_uptime")
            if isinstance(prev_uptime, (int, float)) and prev_uptime < 300:
                reboot_type = "crash_or_freeze"
                confidence = 0.75
                evidence.append("short uptime before reboot — likely crash/freeze")
            else:
                reboot_type = "soft_reboot"
                confidence = 0.70
                evidence.append("clean boot_id change without panic signature")
        else:
            reboot_type = "boot_normal"
            confidence = 0.60

        return {
            "reboot_type": reboot_type,
            "confidence": confidence,
            "evidence": evidence,
            "boot_id": ProcReader.boot_id(),
            "uptime_seconds": ProcReader.uptime()[0],
            "ipmi_sel_sample": ipmi_sel.splitlines()[-5:],
        }
