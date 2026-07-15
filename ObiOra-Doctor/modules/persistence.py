"""Cron jobs and systemd timers audit (persistence / backdoor detection)."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class PersistenceModule(DiagnosticModule):
    """Audit cron and systemd timers for unknown persistence."""

    name = "persistence"
    title = "Persistence"

    _SUSPICIOUS_PATTERNS = (
        "/tmp/",
        "curl ",
        "wget ",
        "bash -i",
        "/dev/tcp/",
        "nc -",
        "python -c",
        "base64 -d",
    )

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        cron_system = self.runner.run(["cat", "/etc/crontab"])
        cron_dirs = self.runner.run(
            ["bash", "-c", "grep -rh . /etc/cron.d /etc/cron.daily /etc/cron.hourly 2>/dev/null | head -100"],
            timeout_seconds=10,
        )
        user_cron = self.runner.run(
            ["bash", "-c", "for u in $(cut -f1 -d: /etc/passwd); do crontab -l -u \"$u\" 2>/dev/null; done | head -80"],
            timeout_seconds=15,
        )
        timers = self.runner.run(
            ["systemctl", "list-timers", "--all", "--no-pager", "--no-legend"],
            timeout_seconds=10,
        )
        timer_lines = [ln.strip() for ln in timers.stdout.splitlines() if ln.strip()]

        all_cron = "\n".join(
            filter(None, [cron_system.stdout, cron_dirs.stdout, user_cron.stdout])
        )
        suspicious = self._find_suspicious_lines(all_cron)

        return {
            "metrics": {
                "cron_lines": len(all_cron.splitlines()),
                "timer_count": len(timer_lines),
                "suspicious_cron_count": len(suspicious),
                "suspicious_cron": suspicious[:5],
            },
            "timers_sample": timer_lines[:10],
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        findings: list[Finding] = []

        if m["suspicious_cron_count"] > 0:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Entrees cron suspectes",
                    "; ".join(m["suspicious_cron"][:3]),
                    "Analyser et supprimer les taches cron non autorisees.",
                    ["cat /etc/crontab", "ls -la /etc/cron.d"],
                )
            )

        if m["timer_count"] > 50:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Nombreux systemd timers",
                    f"{m['timer_count']} timers actifs.",
                    "Auditer systemctl list-timers pour entrees inconnues.",
                    ["systemctl list-timers --all"],
                )
            )

        if not findings:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Persistance cron/timers OK",
                    f"{m['cron_lines']} lignes cron, {m['timer_count']} timers.",
                    "Surveiller les modifications dans /etc/cron.*",
                )
            )
        return findings

    def _find_suspicious_lines(self, text: str) -> list[str]:
        suspicious = []
        for line in text.splitlines():
            low = line.lower()
            if any(p in low for p in self._SUSPICIOUS_PATTERNS):
                suspicious.append(line.strip()[:120])
        return suspicious
