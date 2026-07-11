"""Virtualizor and libvirt/KVM metrics."""

from __future__ import annotations

from typing import Any

from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.subprocess_runner import SubprocessRunner


class VirtualizorSampler(BaseSampler):
    name = "virtualizor"

    def __init__(self, runner: SubprocessRunner) -> None:
        self.runner = runner

    def sample(self) -> dict[str, Any]:
        virt_check = self.runner.run_text(
            ["bash", "-c", "/usr/local/emps/bin/php /usr/local/virtualizor/scripts/virt_check.php 2>/dev/null || virt_check 2>/dev/null"]
        )
        virsh_list = self.runner.run_text(["virsh", "list", "--all"])
        virsh_stats = self.runner.run_text(["virsh", "domstats", "--state", "--cpu", "--balloon", "--block", "--interface"])
        virsh_info = self.runner.run_text(["bash", "-c", "for vm in $(virsh list --all --name 2>/dev/null); do virsh dominfo \"$vm\"; done"])
        qemu_procs = self.runner.run_text(["bash", "-c", "ps aux | grep -E '[q]emu-kvm'"])
        guest_agents = self.runner.run_text(
            ["bash", "-c", "for vm in $(virsh list --name 2>/dev/null); do echo \"=== $vm ===\"; virsh qemu-agent-command \"$vm\" '{\"execute\":\"guest-info\"}' 2>/dev/null; done"]
        )
        cron_status = self.runner.run_text(["systemctl", "is-active", "crond"])
        vm_count = len([line for line in virsh_list.splitlines() if line.strip() and not line.startswith("Id") and "---" not in line])

        return {
            "virt_check": virt_check,
            "virsh_list": virsh_list,
            "virsh_domstats": virsh_stats,
            "virsh_dominfo": virsh_info,
            "qemu_processes": qemu_procs.splitlines()[:20],
            "guest_agent_state": guest_agents,
            "cron_status": cron_status,
            "vm_count": vm_count,
            "running_tasks": self.runner.run_text(["bash", "-c", "ps aux --sort=-%cpu | head -10"]),
        }
