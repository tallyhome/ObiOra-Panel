"""Docker security audit — privileged containers, sockets, images."""

from __future__ import annotations

import json
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class DockerSecurityModule(DiagnosticModule):
    """Security-focused Docker audit."""

    name = "docker_security"
    title = "Docker Security"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        inspect_all = self.runner.run(
            ["docker", "ps", "-aq"],
            timeout_seconds=15,
        )
        if inspect_all.missing or not inspect_all.stdout.strip():
            return {"metrics": {"docker_available": False}}

        privileged = []
        socket_mounts = []
        outdated = []

        for cid in inspect_all.stdout.splitlines()[:30]:
            cid = cid.strip()
            if not cid:
                continue
            detail = self.runner.run(["docker", "inspect", cid, "--format", "{{json .}}"], timeout_seconds=10)
            if not detail.ok:
                continue
            try:
                data = json.loads(detail.stdout)
            except json.JSONDecodeError:
                continue
            name = data.get("Name", cid).lstrip("/")
            host_config = data.get("HostConfig") or {}
            if host_config.get("Privileged"):
                privileged.append(name)
            for mount in data.get("Mounts") or []:
                src = str(mount.get("Source", ""))
                if "/var/run/docker.sock" in src or src == "/var/run/docker.sock":
                    socket_mounts.append(name)
            config = data.get("Config") or {}
            image = config.get("Image", "")
            if image and ":latest" in image:
                outdated.append(name)

        return {
            "metrics": {
                "docker_available": True,
                "container_count": len(inspect_all.stdout.splitlines()),
                "privileged_count": len(privileged),
                "privileged": privileged[:5],
                "docker_sock_mounts": socket_mounts[:5],
                "latest_tag_count": len(outdated),
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        if not m.get("docker_available"):
            return [
                Finding(
                    Severity.INFO,
                    "Docker non utilise",
                    "Aucun conteneur Docker detecte.",
                    "Aucune action requise.",
                )
            ]

        findings: list[Finding] = []
        if m["privileged_count"] > 0:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Conteneurs privileged",
                    ", ".join(m["privileged"]),
                    "Eviter --privileged sauf necessite absolue.",
                    ["docker ps -q | xargs docker inspect --format '{{.Name}} privileged={{.HostConfig.Privileged}}'"],
                )
            )
        if m["docker_sock_mounts"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Montage docker.sock",
                    ", ".join(m["docker_sock_mounts"]),
                    "Un conteneur avec docker.sock peut controler l'hote.",
                    ["docker inspect --format '{{.Name}} {{json .Mounts}}'"],
                )
            )
        if m["latest_tag_count"] > 3:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Images :latest nombreuses",
                    f"{m['latest_tag_count']} conteneur(s) sans tag versionne.",
                    "Epingler des versions d'images pour la reproductibilite.",
                )
            )
        if not findings:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Audit Docker securite OK",
                    f"{m['container_count']} conteneur(s) sans alerte majeure.",
                    "Mettre a jour les images regulierement.",
                )
            )
        return findings
