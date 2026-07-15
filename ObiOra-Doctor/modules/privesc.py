"""SUID/SGID and privilege escalation file audit."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class PrivescModule(DiagnosticModule):
    """Find abnormal SUID/SGID binaries."""

    name = "privesc"
    title = "Privesc"

    _KNOWN_SUID = {
        "/usr/bin/sudo",
        "/usr/bin/su",
        "/usr/bin/passwd",
        "/usr/bin/chfn",
        "/usr/bin/chsh",
        "/usr/bin/gpasswd",
        "/usr/bin/newgrp",
        "/usr/bin/mount",
        "/usr/bin/umount",
        "/usr/bin/pkexec",
        "/usr/lib/dbus-1.0/dbus-daemon-launch-helper",
        "/usr/lib/openssh/ssh-keysign",
    }

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        suid = self.runner.run(
            ["bash", "-c", "find / -xdev \\( -perm -4000 -o -perm -2000 \\) -type f 2>/dev/null | head -80"],
            timeout_seconds=45,
        )
        world_writable = self.runner.run(
            ["bash", "-c", "find /tmp /var/tmp /dev/shm -xdev -type f -perm -0002 2>/dev/null | head -30"],
            timeout_seconds=20,
        )

        suid_files = [f.strip() for f in suid.stdout.splitlines() if f.strip()]
        unknown_suid = [f for f in suid_files if f not in self._KNOWN_SUID]
        ww_files = [f.strip() for f in world_writable.stdout.splitlines() if f.strip()]

        return {
            "metrics": {
                "suid_count": len(suid_files),
                "unknown_suid_count": len(unknown_suid),
                "unknown_suid": unknown_suid[:10],
                "world_writable_count": len(ww_files),
                "world_writable": ww_files[:5],
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        findings: list[Finding] = []

        if m["unknown_suid_count"] > 0:
            level = Severity.CRITICAL if m["unknown_suid_count"] > 5 else Severity.WARNING
            findings.append(
                Finding(
                    level,
                    "Binaires SUID/SGID inhabituels",
                    ", ".join(m["unknown_suid"][:5]),
                    "Verifier l'origine de chaque binaire SUID non standard.",
                    ["find / -xdev -perm -4000 -type f 2>/dev/null"],
                )
            )

        if m["world_writable_count"] > 10:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Fichiers world-writable dans /tmp",
                    f"{m['world_writable_count']} fichier(s) detecte(s).",
                    "Nettoyer les fichiers temporaires suspects.",
                    ["find /tmp -type f -perm -0002"],
                )
            )

        if not findings:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Audit SUID/SGID OK",
                    f"{m['suid_count']} binaire(s) SUID/SGID connus.",
                    "Re-scanner apres installation de packages.",
                )
            )
        return findings
