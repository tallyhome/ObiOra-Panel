"""Enterprise virtualization plugins: libvirt, QEMU (separate from Virtualizor)."""

from __future__ import annotations

from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector


class LibvirtCollector(TimedCollector):
    name = "libvirt"
    priority = 80

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        vm_list = self.timed_command("virsh_list", ["virsh", "list", "--all"], timeout=self.settings.incident.virsh_timeout_seconds)
        return {
            **self.collect_meta(),
            "service": self.timed_command("libvirtd", ["systemctl", "is-active", "libvirtd"]),
            "virsh_list": vm_list,
            "virsh_domstats": self.timed_command(
                "virsh_domstats",
                ["virsh", "domstats", "--state", "--cpu", "--balloon", "--block", "--interface"],
                timeout=self.settings.incident.virsh_timeout_seconds,
            ),
            "virsh_domblkstat": self.timed_command(
                "virsh_domblkstat",
                ["bash", "-c", "for vm in $(virsh list --name 2>/dev/null); do echo \"=== $vm ===\"; virsh domblkstat \"$vm\" 2>/dev/null; done"],
            ),
            "virsh_dommemstat": self.timed_command(
                "virsh_dommemstat",
                ["bash", "-c", "for vm in $(virsh list --name 2>/dev/null); do echo \"=== $vm ===\"; virsh dommemstat \"$vm\" 2>/dev/null; done"],
            ),
            "virsh_domifstat": self.timed_command(
                "virsh_domifstat",
                ["bash", "-c", "for vm in $(virsh list --name 2>/dev/null); do echo \"=== $vm ===\"; virsh domifstat \"$vm\" 2>/dev/null; done"],
            ),
        }


class QemuCollector(TimedCollector):
    name = "qemu"
    priority = 85

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "processes": self.timed_command(
                "qemu_ps",
                ["bash", "-c", "ps -eo pid,ppid,comm,stat,%cpu,%mem,rss,vsz,nlwp,etime,args | grep -E '[q]emu-kvm'"],
            ),
            "threads": self.timed_command(
                "qemu_threads",
                ["bash", "-c", "for pid in $(pgrep -f qemu-kvm); do echo \"=== PID $pid ===\"; ls /proc/$pid/task 2>/dev/null | wc -l; done"],
            ),
            "open_fds": self.timed_command(
                "qemu_fds",
                ["bash", "-c", "for pid in $(pgrep -f qemu-kvm); do echo \"=== PID $pid ===\"; ls /proc/$pid/fd 2>/dev/null | wc -l; done"],
            ),
        }
