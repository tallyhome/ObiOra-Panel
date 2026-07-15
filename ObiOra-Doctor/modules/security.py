"""Security audit diagnostic module."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class SecurityModule(DiagnosticModule):
    """Collect Linux security indicators (SSH, firewall helpers, updates)."""

    name = "security"
    title = "Security"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect SSH, SELinux, fail2ban, updates and privileged user indicators."""

        ssh_config = self._read_sshd_config()
        selinux = self.runner.run(["getenforce"])
        sudoers = self.runner.run(["getent", "group", "sudo"])
        wheel = self.runner.run(["getent", "group", "wheel"])
        fail2ban_active, _ = systemd_active(self.runner, "fail2ban")
        unattended = self._check_unattended_upgrades()
        pending_updates = self._pending_security_updates()
        sudo_users = self._sudo_users()
        apparmor = self._apparmor_status()
        auditd_active, _ = systemd_active(self.runner, "auditd")
        mariadb_bind = self._service_bind("mysqld", 3306)
        redis_bind = self._service_bind("redis", 6379)
        reboot_required = self._reboot_required()

        return {
            "selinux": selinux.to_dict(),
            "metrics": {
                "permit_root_login": ssh_config.get("permit_root_login", False),
                "password_auth": ssh_config.get("password_auth", False),
                "ssh_port": ssh_config.get("port", 22),
                "selinux_enforcing": selinux.stdout.strip().lower() == "enforcing",
                "selinux_available": selinux.ok,
                "sudo_group": bool(sudoers.stdout or wheel.stdout),
                "fail2ban_active": fail2ban_active,
                "unattended_upgrades": unattended,
                "pending_security_updates": pending_updates.get("count", 0),
                "pending_updates_tool": pending_updates.get("tool", ""),
                "sudo_user_count": len(sudo_users),
                "sudo_users": sudo_users[:10],
                "apparmor_active": apparmor.get("active", False),
                "apparmor_profiles": apparmor.get("profiles", 0),
                "auditd_active": auditd_active,
                "mariadb_public": mariadb_bind.get("public", False),
                "redis_public": redis_bind.get("public", False),
                "reboot_required": reboot_required,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build security findings."""

        metrics = raw_data["metrics"]
        findings: list[Finding] = []

        if metrics["permit_root_login"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "SSH root login actif",
                    "PermitRootLogin yes detecte — information uniquement.",
                    "Evaluation manuelle si vous souhaitez restreindre root SSH.",
                    ["grep PermitRootLogin /etc/ssh/sshd_config"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.INFO,
                    "SSH root login restreint",
                    "Aucune autorisation root SSH evidente detectee.",
                    "Aucune action requise.",
                    ["grep PermitRootLogin /etc/ssh/sshd_config"],
                )
            )

        if metrics["password_auth"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Authentification SSH par mot de passe active",
                    "PasswordAuthentication yes detecte — information uniquement.",
                    "Evaluation manuelle si vous souhaitez passer aux cles SSH.",
                    ["grep PasswordAuthentication /etc/ssh/sshd_config"],
                )
            )

        if metrics["ssh_port"] == 22:
            findings.append(
                Finding(
                    Severity.INFO,
                    "SSH sur port par defaut (22)",
                    "Le port SSH standard est utilise.",
                    "Envisager un port SSH non standard + fail2ban.",
                    ["grep ^Port /etc/ssh/sshd_config"],
                )
            )

        if raw_data["selinux"].get("ok"):
            level = Severity.INFO if metrics["selinux_enforcing"] else Severity.WARNING
            findings.append(
                Finding(
                    level,
                    "SELinux",
                    f"Mode SELinux: {raw_data['selinux'].get('stdout', 'unknown')}",
                    "Verifier la politique SELinux en production.",
                    ["getenforce"],
                )
            )

        if not metrics["fail2ban_active"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Fail2ban inactif",
                    "Le service fail2ban n'est pas actif.",
                    "Activer fail2ban pour proteger SSH et services web.",
                    ["systemctl status fail2ban", "systemctl enable --now fail2ban"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Fail2ban actif",
                    "Protection brute-force active.",
                    "Verifier les jails SSH et nginx.",
                    ["fail2ban-client status"],
                )
            )

        pending = metrics.get("pending_security_updates", 0)
        if pending > 0:
            level = Severity.CRITICAL if pending > 20 else Severity.WARNING
            findings.append(
                Finding(
                    level,
                    "Mises a jour securite en attente",
                    f"{pending} mise(s) a jour detectee(s) ({metrics.get('pending_updates_tool', '')}).",
                    "Appliquer les correctifs de securite systeme.",
                    ["dnf updateinfo list security", "apt list --upgradable"],
                )
            )

        if metrics["sudo_user_count"] > 3:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Nombreuses entrees sudo/wheel",
                    f"{metrics['sudo_user_count']} utilisateur(s) avec privileges eleves.",
                    "Auditer les comptes sudo et retirer les acces inutiles.",
                    ["getent group sudo", "getent group wheel"],
                )
            )

        if metrics["mariadb_public"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "MariaDB/MySQL expose publiquement",
                    "Le port 3306 ecoute sur 0.0.0.0.",
                    "Restreindre bind-address a 127.0.0.1.",
                    ["ss -tlnp | grep 3306", "grep bind-address /etc/my.cnf"],
                )
            )

        if metrics["redis_public"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Redis expose publiquement",
                    "Le port 6379 ecoute sur 0.0.0.0.",
                    "Configurer bind 127.0.0.1 et requirepass.",
                    ["ss -tlnp | grep 6379", "grep bind /etc/redis/redis.conf"],
                )
            )

        if not metrics["unattended_upgrades"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Mises a jour automatiques non detectees",
                    "unattended-upgrades ou dnf-automatic absent.",
                    "Envisager l'activation des mises a jour automatiques de securite.",
                    ["systemctl status unattended-upgrades"],
                )
            )

        if not metrics["apparmor_active"] and Path("/sys/module/apparmor").exists():
            findings.append(
                Finding(
                    Severity.WARNING,
                    "AppArmor disponible mais inactif",
                    "Le module AppArmor est present mais non enforce.",
                    "Activer AppArmor si compatible avec la stack.",
                    ["aa-status"],
                )
            )

        if not metrics["auditd_active"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Auditd inactif",
                    "Le daemon auditd n'est pas actif.",
                    "Activer auditd pour la tracabilite des actions sensibles.",
                    ["systemctl status auditd"],
                )
            )

        if metrics.get("reboot_required"):
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Redemarrage requis",
                    "Un reboot est necessaire (kernel ou libs mises a jour).",
                    "Planifier un reboot maintenance.",
                    ["needs-restarting -r 2>/dev/null || cat /var/run/reboot-required"],
                )
            )

        return findings

    def _read_sshd_config(self) -> dict[str, Any]:
        ssh_path = Path("/etc/ssh/sshd_config")
        if not ssh_path.exists():
            return {"permit_root_login": False, "password_auth": False, "port": 22}

        content = ssh_path.read_text(encoding="utf-8", errors="ignore")
        permit_root = False
        password_auth = False
        port = 22

        for line in content.splitlines():
            line = line.strip()
            if line.startswith("#") or not line:
                continue
            if re.match(r"PermitRootLogin\s+yes", line, re.I):
                permit_root = True
            if re.match(r"PasswordAuthentication\s+yes", line, re.I):
                password_auth = True
            port_match = re.match(r"Port\s+(\d+)", line, re.I)
            if port_match:
                port = int(port_match.group(1))

        return {"permit_root_login": permit_root, "password_auth": password_auth, "port": port}

    def _check_unattended_upgrades(self) -> bool:
        for unit in ["unattended-upgrades", "dnf-automatic.timer", "yum-cron"]:
            active, _ = systemd_active(self.runner, unit)
            if active:
                return True
        return Path("/etc/apt/apt.conf.d/50unattended-upgrades").exists()

    def _pending_security_updates(self) -> dict[str, Any]:
        dnf = self.runner.run(["bash", "-c", "dnf updateinfo list security 2>/dev/null | grep -c ELSA || true"])
        if dnf.ok and dnf.stdout.strip().isdigit() and int(dnf.stdout.strip()) > 0:
            return {"count": int(dnf.stdout.strip()), "tool": "dnf"}

        apt = self.runner.run(
            ["bash", "-c", "apt list --upgradable 2>/dev/null | grep -ci security || true"],
            timeout_seconds=30,
        )
        if apt.ok and apt.stdout.strip().isdigit():
            count = int(apt.stdout.strip())
            if count > 0:
                return {"count": count, "tool": "apt"}

        yum = self.runner.run(["bash", "-c", "yum check-update --security -q 2>/dev/null | wc -l"])
        if yum.ok and yum.stdout.strip().isdigit():
            count = max(0, int(yum.stdout.strip()) - 1)
            if count > 0:
                return {"count": count, "tool": "yum"}

        return {"count": 0, "tool": ""}

    def _sudo_users(self) -> list[str]:
        users: list[str] = []
        for group in ["sudo", "wheel"]:
            result = self.runner.run(["getent", "group", group])
            if not result.ok or not result.stdout.strip():
                continue
            parts = result.stdout.strip().split(":")
            if len(parts) >= 4 and parts[3]:
                users.extend(u.strip() for u in parts[3].split(",") if u.strip())
        return list(dict.fromkeys(users))

    def _apparmor_status(self) -> dict[str, Any]:
        result = self.runner.run(["aa-status"])
        if result.missing or not result.ok:
            return {"active": False, "profiles": 0}
        profiles = result.stdout.lower().count("profile")
        return {"active": "enforce" in result.stdout.lower(), "profiles": profiles}

    def _service_bind(self, service: str, port: int) -> dict[str, Any]:
        active, _ = systemd_active(self.runner, service)
        if not active:
            active_srv, _ = systemd_active(self.runner, f"{service}-server")
            if not active_srv:
                return {"public": False, "port": port}

        result = self.runner.run(["ss", "-tlnH"])
        if not result.ok:
            return {"public": False, "port": port}

        for line in result.stdout.splitlines():
            if f":{port}" not in line:
                continue
            public = "0.0.0.0" in line or "[::]" in line
            return {"public": public, "port": port}

        return {"public": False, "port": port}

    def _reboot_required(self) -> bool:
        if Path("/var/run/reboot-required").exists():
            return True
        nr = self.runner.run(["needs-restarting", "-r"])
        if nr.ok and nr.stdout.strip():
            return True
        return False
