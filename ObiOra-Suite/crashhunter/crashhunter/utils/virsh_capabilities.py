"""Detect virsh domstats capabilities once and cache them."""

from __future__ import annotations

import logging
import re
from typing import ClassVar

from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.virsh")

_PREFERRED_DOMSTATS_FLAGS = (
    "--state",
    "--cpu",
    "--balloon",
    "--block",
    "--interface",
    "--cpu-total",
    "--vcpu",
)


class VirshCapabilities:
    """Build virsh commands compatible with the installed libvirt CLI."""

    _domstats_flags: ClassVar[list[str] | None] = None
    _domstats_usable: ClassVar[bool | None] = None

    @classmethod
    def reset_cache(cls) -> None:
        cls._domstats_flags = None
        cls._domstats_usable = None

    @classmethod
    def domstats_flags(cls, runner: SubprocessRunner) -> list[str]:
        if cls._domstats_flags is not None:
            return list(cls._domstats_flags)

        result = runner.run(["virsh", "domstats", "--help"], timeout=5.0)
        help_text = f"{result.stdout}\n{result.stderr}"
        supported: list[str] = []
        for flag in _PREFERRED_DOMSTATS_FLAGS:
            if re.search(rf"{re.escape(flag)}\b", help_text):
                supported.append(flag)

        cls._domstats_usable = bool(supported) and result.returncode in (0, 1)
        cls._domstats_flags = supported
        logger.debug("virsh domstats flags detected: %s", supported)
        return list(supported)

    @classmethod
    def domstats_command(cls, runner: SubprocessRunner) -> list[str]:
        flags = cls.domstats_flags(runner)
        if not flags:
            return ["virsh", "list", "--all"]
        return ["virsh", "domstats", *flags]

    @classmethod
    def domstats_available(cls, runner: SubprocessRunner) -> bool:
        if cls._domstats_usable is None:
            cls.domstats_flags(runner)
        return bool(cls._domstats_usable)
