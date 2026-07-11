"""Version signature for reports — software and hardware fingerprint."""

from __future__ import annotations

import platform
import sys
from typing import Any

from crashhunter import __version__
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner


def collect_version_signature(runner: SubprocessRunner | None = None) -> dict[str, Any]:
    """Collect CrashHunter, kernel, Virtualizor, libvirt, QEMU, Python, hardware."""
    runner = runner or SubprocessRunner(default_timeout=3.0)
    return {
        "crashhunter": __version__,
        "python": sys.version.split()[0],
        "kernel": ProcReader.kernel_version(),
        "libvirt": _first_line(runner.run_text(["virsh", "version"])),
        "qemu": _first_line(runner.run_text(["qemu-system-x86_64", "--version"])),
        "virtualizor": _detect_virtualizor_version(runner),
        "hardware_model": _read_dmi("product_name"),
        "bios_version": _read_dmi("bios_version"),
        "platform": platform.platform(),
        "hostname": platform.node(),
    }


def _first_line(text: str) -> str:
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    return lines[0] if lines else "unknown"


def _read_dmi(field: str) -> str:
  path = f"/sys/class/dmi/id/{field.replace('_', '_')}"
  mapping = {
      "product_name": "/sys/class/dmi/id/product_name",
      "bios_version": "/sys/class/dmi/id/bios_version",
  }
  return ProcReader.read_text(mapping.get(field, path), "unknown")


def _detect_virtualizor_version(runner: SubprocessRunner) -> str:
    for cmd in (
        ["bash", "-c", "cat /usr/local/virtualizor/version 2>/dev/null"],
        ["bash", "-c", "/usr/local/emps/bin/php -r 'echo @file_get_contents(\"/usr/local/virtualizor/version\");' 2>/dev/null"],
    ):
        text = runner.run_text(cmd)
        if text.strip():
            return text.strip().splitlines()[0]
    return "unknown"
