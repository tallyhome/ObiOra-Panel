"""Obiora Panel / seedbox specific security checks."""

from __future__ import annotations

import os
import re
from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class ObioraModule(DiagnosticModule):
    """Audit Obiora agents, panel files and exposed services."""

    name = "obiora"
    title = "Obiora"

    _SUSPICIOUS_PORTS = {10000, 8080, 8888, 8443, 3306, 6379, 5432, 9100}

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect Obiora-specific security indicators."""

        agent_listen = self._agent_port_exposed()
        token_perms = self._check_file_mode("/opt/obiora-agent/config/agent.json", 0o600)
        doctor_env = self._check_file_mode("/opt/obiora-doctor-agent/agent.env", 0o600)
        panel_env = self._find_panel_env()
        nginx_headers = self._nginx_security_headers()
        obiora_services = self._obiora_services()
        open_ports = self._listening_ports()

        return {
            "metrics": {
                "agent_port_exposed": agent_listen.get("public", False),
                "agent_port": agent_listen.get("port", 9100),
                "agent_bind": agent_listen.get("bind", ""),
                "agent_token_secure": token_perms.get("secure", True),
                "doctor_env_secure": doctor_env.get("secure", True),
                "panel_env_secure": panel_env.get("secure", True),
                "panel_env_path": panel_env.get("path", ""),
                "nginx_hsts": nginx_headers.get("hsts", False),
                "nginx_x_frame": nginx_headers.get("x_frame", False),
                "nginx_x_content_type": nginx_headers.get("x_content_type", False),
                "obiora_agent_active": obiora_services.get("obiora-agent", False),
                "obiora_queue_active": obiora_services.get("obiora-queue", False),
                "doctor_timer_active": obiora_services.get("doctor_timer", False),
                "suspicious_open_ports": open_ports.get("suspicious", []),
                "all_listening_ports": open_ports.get("all", []),
            },
            "raw": {
                "agent_listen": agent_listen,
                "token_perms": token_perms,
                "nginx_headers": nginx_headers,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build Obiora security findings."""

        metrics = raw_data["metrics"]
        findings: list[Finding] = []

        if metrics["agent_port_exposed"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Port agent Obiora expose publiquement",
                    f"Le port {metrics['agent_port']} ecoute sur {metrics['agent_bind'] or '0.0.0.0'}. "
                    "L'endpoint /api/v1/execute permet l'execution de commandes.",
                    "Restreindre l'acces au panel uniquement (firewall ou bind 127.0.0.1).",
                    ["ss -tlnp | grep :9100", "ufw status"],
                )
            )
        elif metrics.get("obiora_agent_active"):
            findings.append(
                Finding(
                    Severity.INFO,
                    "Port agent Obiora restreint",
                    "L'agent Obiora ne semble pas expose publiquement.",
                    "Verifier periodiquement les regles firewall.",
                    ["ss -tlnp | grep obiora"],
                )
            )

        if not metrics["agent_token_secure"] and Path("/opt/obiora-agent/config/agent.json").exists():
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Permissions agent.json trop permissives",
                    "Le fichier token agent devrait etre chmod 600.",
                    "Appliquer chmod 600 sur agent.json.",
                    ["stat -c '%a' /opt/obiora-agent/config/agent.json"],
                )
            )

        if not metrics["doctor_env_secure"] and Path("/opt/obiora-doctor-agent/agent.env").exists():
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Permissions agent.env Doctor trop permissives",
                    "Le fichier agent.env devrait etre chmod 600.",
                    "Appliquer chmod 600 sur agent.env.",
                    ["stat -c '%a' /opt/obiora-doctor-agent/agent.env"],
                )
            )

        panel_path = metrics.get("panel_env_path") or ""
        if panel_path and not metrics["panel_env_secure"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Permissions .env panel trop permissives",
                    f"Le fichier {panel_path} est lisible par d'autres utilisateurs.",
                    "Appliquer chmod 600 sur le .env du panel.",
                    [f"stat -c '%a %U' {panel_path}"],
                )
            )

        if metrics.get("obiora_queue_active") is False and metrics.get("obiora_agent_active"):
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Service obiora-queue inactif",
                    "La file d'attente panel est arretee.",
                    "Verifier systemctl status obiora-queue.",
                    ["systemctl status obiora-queue"],
                )
            )

        missing_headers = []
        if not metrics["nginx_hsts"]:
            missing_headers.append("HSTS")
        if not metrics["nginx_x_frame"]:
            missing_headers.append("X-Frame-Options")
        if not metrics["nginx_x_content_type"]:
            missing_headers.append("X-Content-Type-Options")

        if missing_headers:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Headers securite nginx incomplets",
                    "Headers manquants: " + ", ".join(missing_headers),
                    "Configurer les headers de securite dans nginx pour le panel.",
                    ["grep -r add_header /etc/nginx/"],
                )
            )

        suspicious = metrics.get("suspicious_open_ports") or []
        if suspicious:
            ports_str = ", ".join(str(p) for p in suspicious)
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Ports sensibles ouverts",
                    f"Ports detectes: {ports_str}",
                    "Verifier si ces ports doivent etre publics (Webmin, BDD, agent…).",
                    ["ss -tlnp"],
                )
            )

        doctor_web = self._doctor_web_bind()
        if doctor_web.get("exposed"):
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Interface web Doctor exposee",
                    f"Doctor web ecoute sur {doctor_web.get('bind', '0.0.0.0')}.",
                    "Lier Doctor web sur 127.0.0.1 uniquement (tunnel SSH).",
                    ["ss -tlnp | grep 876"],
                )
            )

        return findings

    def _agent_port_exposed(self) -> dict[str, Any]:
        result = self.runner.run(["ss", "-tlnp"])
        if not result.ok:
            return {"public": False, "port": 9100, "bind": ""}

        for line in result.stdout.splitlines():
            if ":9100" not in line:
                continue
            public = any(bind in line for bind in ["0.0.0.0:9100", "[::]:9100", "*:9100"])
            bind = "0.0.0.0" if "0.0.0.0:9100" in line else line.split()[3] if len(line.split()) > 3 else ""
            return {"public": public, "port": 9100, "bind": bind}

        return {"public": False, "port": 9100, "bind": ""}

    def _check_file_mode(self, path: str, expected: int) -> dict[str, Any]:
        p = Path(path)
        if not p.exists():
            return {"exists": False, "secure": True, "mode": None}

        try:
            mode = p.stat().st_mode & 0o777
            secure = mode <= expected and (mode & 0o077) == 0
            return {"exists": True, "secure": secure, "mode": oct(mode)}
        except OSError:
            return {"exists": True, "secure": False, "mode": None}

    def _find_panel_env(self) -> dict[str, Any]:
        candidates = [
            "/opt/obiora-panel/.env",
            "/var/www/obiora-panel/.env",
            "/var/www/html/.env",
        ]
        base = os.environ.get("OBIORA_PANEL_PATH", "")
        if base:
            candidates.insert(0, f"{base}/.env")

        for candidate in candidates:
            p = Path(candidate)
            if p.exists():
                check = self._check_file_mode(candidate, 0o600)
                check["path"] = candidate
                return check

        return {"exists": False, "secure": True, "path": ""}

    def _nginx_security_headers(self) -> dict[str, bool]:
        configs = ["/etc/nginx/nginx.conf"]
        sites = Path("/etc/nginx/sites-enabled")
        if sites.is_dir():
            configs.extend(str(p) for p in sites.iterdir() if p.is_file())

        content = ""
        for cfg in configs:
            p = Path(cfg)
            if p.exists():
                try:
                    content += p.read_text(encoding="utf-8", errors="ignore").lower()
                except OSError:
                    pass

        return {
            "hsts": "strict-transport-security" in content,
            "x_frame": "x-frame-options" in content,
            "x_content_type": "x-content-type-options" in content,
        }

    def _obiora_services(self) -> dict[str, bool]:
        agent, _ = systemd_active(self.runner, "obiora-agent")
        queue, _ = systemd_active(self.runner, "obiora-queue")
        timer, _ = systemd_active(self.runner, "obiora-doctor-agent.timer")
        return {
            "obiora-agent": agent,
            "obiora-queue": queue,
            "doctor_timer": timer,
        }

    def _listening_ports(self) -> dict[str, Any]:
        result = self.runner.run(["ss", "-tlnH"])
        if not result.ok:
            return {"all": [], "suspicious": []}

        ports: list[int] = []
        suspicious: list[int] = []
        for line in result.stdout.splitlines():
            match = re.search(r":(\d+)\s", line)
            if not match:
                continue
            port = int(match.group(1))
            if port not in ports:
                ports.append(port)
            if port in self._SUSPICIOUS_PORTS and port not in suspicious:
                if "0.0.0.0" in line or "[::]" in line or "*" in line:
                    suspicious.append(port)

        return {"all": sorted(ports), "suspicious": sorted(suspicious)}

    def _doctor_web_bind(self) -> dict[str, Any]:
        result = self.runner.run(["ss", "-tlnp"])
        if not result.ok:
            return {"exposed": False, "bind": ""}

        for line in result.stdout.splitlines():
            if re.search(r":876[0-9]\s", line):
                exposed = "127.0.0.1" not in line
                return {"exposed": exposed, "bind": line.split()[3] if len(line.split()) > 3 else ""}

        return {"exposed": False, "bind": ""}
