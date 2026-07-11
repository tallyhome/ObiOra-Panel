"""Network interfaces, TCP/UDP, bridges, bonds."""

from __future__ import annotations

from typing import Any

from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner


class NetworkSampler(BaseSampler):
    name = "network"

    def __init__(self, runner: SubprocessRunner) -> None:
        self.runner = runner

    def sample(self) -> dict[str, Any]:
        tcp = ProcReader.tcp_sockets()
        udp = ProcReader.udp_sockets()
        return {
            "interfaces": ProcReader.netdev(),
            "ip_link_stats": self.runner.run_text(["ip", "-s", "link"]),
            "tcp_connections": len(tcp),
            "udp_sockets": len(udp),
            "tcp_established": sum(1 for s in tcp if s["state"] == "ESTABLISHED"),
            "tcp_listen": sum(1 for s in tcp if s["state"] == "LISTEN"),
            "tcp_sockets_sample": tcp[:50],
            "udp_sockets_sample": udp[:30],
            "bridge_state": self.runner.run_text(["bridge", "link"]),
            "bond_state": self.runner.run_text(["cat", "/proc/net/bonding/bond0"]),
            "ss_summary": self.runner.run_text(["ss", "-s"]),
        }
