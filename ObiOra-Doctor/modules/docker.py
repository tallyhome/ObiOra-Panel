"""Docker diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class DockerModule(DiagnosticModule):
    """Collect and diagnose Docker state."""

    name = "docker"
    title = "Docker"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect Docker daemon and container information."""

        version = self.runner.run(["docker", "version", "--format", "{{json .}}"])
        info = self.runner.run(["docker", "info", "--format", "{{json .}}"])
        containers = self.runner.run(
            ["docker", "ps", "-a", "--format", "{{.Names}}\t{{.Status}}"]
        )
        return {
            "version": version.to_dict(),
            "info": info.to_dict(),
            "containers": containers.to_dict(),
            "metrics": {
                "docker_available": not version.missing,
                "daemon_reachable": info.ok,
                "container_count": self._count_lines(containers.stdout),
                "unhealthy_or_restarting": self._problem_containers(containers.stdout),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build Docker findings from collected raw data."""

        metrics = raw_data["metrics"]
        findings: list[Finding] = []

        if not metrics["docker_available"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Docker non detecte",
                    "La commande docker n'est pas disponible sur ce serveur.",
                    "Aucune action requise si Docker n'est pas utilise.",
                    ["which docker"],
                )
            )
            return findings

        if not metrics["daemon_reachable"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Daemon Docker inaccessible",
                    raw_data["info"]["stderr"] or "docker info n'a pas abouti.",
                    "Verifier le service Docker et les permissions utilisateur.",
                    ["systemctl status docker", "docker info"],
                )
            )
            return findings

        findings.append(
            Finding(
                Severity.INFO,
                "Docker operationnel",
                f"{metrics['container_count']} conteneur(s) detecte(s).",
                "Aucune action requise.",
                ["docker ps -a"],
            )
        )

        problem_containers = metrics["unhealthy_or_restarting"]
        if problem_containers:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Conteneurs Docker a verifier",
                    "Conteneurs problematiques: " + ", ".join(problem_containers),
                    "Inspecter les logs et politiques de redemarrage.",
                    ["docker ps -a", "docker logs <container>"],
                )
            )

        return findings

    @staticmethod
    def _count_lines(output: str) -> int:
        """Count non-empty lines in command output."""

        return len([line for line in output.splitlines() if line.strip()])

    @staticmethod
    def _problem_containers(output: str) -> list[str]:
        """Return containers with unhealthy or restarting status."""

        problems: list[str] = []
        for line in output.splitlines():
            name, _, status = line.partition("\t")
            normalized = status.lower()
            if "unhealthy" in normalized or "restarting" in normalized or "exited" in normalized:
                problems.append(name)
        return problems
