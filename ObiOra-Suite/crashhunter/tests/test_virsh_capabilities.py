"""virsh domstats capability detection tests."""

from __future__ import annotations

from crashhunter.utils.subprocess_runner import CommandResult, SubprocessRunner
from crashhunter.utils.virsh_capabilities import VirshCapabilities


class FakeRunner(SubprocessRunner):
    def __init__(self, help_text: str) -> None:
        super().__init__()
        self.help_text = help_text

    def run(self, command, timeout=None, *, use_process_group=True):  # type: ignore[no-untyped-def]
        if command[:2] == ["virsh", "domstats"]:
            return CommandResult(command=command, stdout=self.help_text, stderr="", returncode=0)
        return CommandResult(command=command, stdout="", stderr="", returncode=0)


def test_domstats_without_cpu_flag() -> None:
    VirshCapabilities.reset_cache()
    runner = FakeRunner("Usage: domstats [--state] [--balloon] [--block]\n")
    flags = VirshCapabilities.domstats_flags(runner)
    assert "--cpu" not in flags
    assert "--state" in flags
    cmd = VirshCapabilities.domstats_command(runner)
    assert "--cpu" not in cmd


def test_domstats_with_cpu_flag() -> None:
    VirshCapabilities.reset_cache()
    runner = FakeRunner("Usage: domstats [--state] [--cpu] [--balloon]\n")
    flags = VirshCapabilities.domstats_flags(runner)
    assert "--cpu" in flags


def test_capability_cache() -> None:
    VirshCapabilities.reset_cache()
    runner = FakeRunner("Usage: domstats [--state]\n")
    first = VirshCapabilities.domstats_flags(runner)
    second = VirshCapabilities.domstats_flags(runner)
    assert first == second
