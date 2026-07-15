"""System accounts and SSH keys audit."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class AccountsModule(DiagnosticModule):
    """Detect suspicious system accounts and authorized_keys."""

    name = "accounts"
    title = "Accounts"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        passwd = self.runner.run(["cat", "/etc/passwd"])
        shadow_readable = self.runner.run(["test", "-r", "/etc/shadow"])
        uid_zero = []
        shell_users = []
        nologin_with_shell = []

        if passwd.ok:
            for line in passwd.stdout.splitlines():
                parts = line.split(":")
                if len(parts) < 7:
                    continue
                user, _x, uid, _gid, _gecos, _home, shell = parts[:7]
                if uid == "0":
                    uid_zero.append(user)
                if shell not in ("/usr/sbin/nologin", "/bin/false", "/sbin/nologin"):
                    shell_users.append(user)

        auth_keys_paths = self._find_authorized_keys()
        unknown_keys = sum(1 for p in auth_keys_paths if p.get("key_count", 0) > 0)

        return {
            "metrics": {
                "uid_zero_accounts": uid_zero,
                "uid_zero_count": len(uid_zero),
                "shell_user_count": len(shell_users),
                "authorized_keys_files": len(auth_keys_paths),
                "total_auth_keys": unknown_keys,
                "shadow_readable": shadow_readable.ok,
            },
            "auth_keys": auth_keys_paths[:10],
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        findings: list[Finding] = []

        if m["uid_zero_count"] > 1:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Plusieurs comptes UID 0",
                    "Comptes root-equivalents: " + ", ".join(m["uid_zero_accounts"]),
                    "Auditer et supprimer les comptes UID 0 non legitimes.",
                    ["awk -F: '$3==0 {print}' /etc/passwd"],
                )
            )

        if m["shell_user_count"] > 15:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Nombreux comptes avec shell",
                    f"{m['shell_user_count']} comptes avec shell de connexion.",
                    "Desactiver les comptes inutilises (usermod -s /usr/sbin/nologin).",
                    ["awk -F: '$7 !~ /nologin|false/ {print $1}' /etc/passwd"],
                )
            )

        if m["total_auth_keys"] > 0:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Cles SSH authorized_keys detectees",
                    f"{m['authorized_keys_files']} fichier(s), {m['total_auth_keys']} entree(s) totales.",
                    "Verifier que chaque cle est connue et autorisee.",
                    ["find /root /home -name authorized_keys 2>/dev/null"],
                )
            )

        for entry in raw_data.get("auth_keys", []):
            if entry.get("world_readable"):
                findings.append(
                    Finding(
                        Severity.WARNING,
                        "authorized_keys world-readable",
                        f"Fichier: {entry.get('path')}",
                        "Appliquer chmod 600 sur authorized_keys.",
                        [f"chmod 600 {entry.get('path')}"],
                    )
                )

        if not findings:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Comptes systeme OK",
                    "Aucune anomalie evidente sur les comptes.",
                    "Revoir periodiquement les acces sudo et SSH.",
                )
            )
        return findings

    def _find_authorized_keys(self) -> list[dict[str, Any]]:
        result = self.runner.run(
            ["bash", "-c", "find /root /home -name authorized_keys -type f 2>/dev/null | head -20"],
            timeout_seconds=15,
        )
        paths = [p.strip() for p in result.stdout.splitlines() if p.strip()]
        entries = []
        for path in paths:
            p = Path(path)
            try:
                content = p.read_text(encoding="utf-8", errors="ignore")
                keys = [ln for ln in content.splitlines() if ln.strip() and not ln.startswith("#")]
                mode = p.stat().st_mode & 0o777
                entries.append(
                    {
                        "path": path,
                        "key_count": len(keys),
                        "world_readable": (mode & 0o044) != 0,
                    }
                )
            except OSError:
                continue
        return entries
