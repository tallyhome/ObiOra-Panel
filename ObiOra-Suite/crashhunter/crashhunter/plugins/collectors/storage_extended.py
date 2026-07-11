"""Enterprise storage plugins: LVM, XFS."""

from __future__ import annotations

from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector


class LvmCollector(TimedCollector):
    name = "lvm"
    priority = 130

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "pvs": self.timed_command("pvs", ["pvs", "--noheadings", "-o", "pv_name,vg_name,pv_size,pv_free"]),
            "vgs": self.timed_command("vgs", ["vgs", "--noheadings", "-o", "vg_name,pv_count,lv_count,vg_size,vg_free"]),
            "lvs": self.timed_command("lvs", ["lvs", "--noheadings", "-o", "lv_name,vg_name,lv_size,lv_attr,devices"]),
            "dmsetup": self.timed_command("dmsetup", ["dmsetup", "ls", "--tree"]),
            "dmsetup_table": self.timed_command("dmsetup_table", ["dmsetup", "table"]),
        }


class XfsCollector(TimedCollector):
    name = "xfs"
    priority = 135

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "xfs_info": self.timed_command(
                "xfs_info",
                ["bash", "-c", "for m in $(mount -t xfs | awk '{print $1}'); do echo \"=== $m ===\"; xfs_info \"$m\" 2>/dev/null; done"],
            ),
            "xfs_spaceman": self.timed_command(
                "xfs_spaceman",
                ["bash", "-c", "for m in $(mount -t xfs | awk '{print $1}'); do echo \"=== $m ===\"; xfs_spaceman -c 'full' \"$m\" 2>/dev/null; done"],
            ),
            "xfs_errors": self.timed_command(
                "xfs_errors",
                ["bash", "-c", "dmesg | grep -i xfs | tail -30"],
            ),
            "xfs_mounts": self.timed_command("mount_xfs", ["mount", "-t", "xfs"]),
        }
