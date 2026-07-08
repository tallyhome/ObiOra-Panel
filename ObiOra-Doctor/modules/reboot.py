"""Reboot analysis and prediction module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class RebootModule(DiagnosticModule):
    """Analyze reboot history, causes and pending reboot requirements."""

    name = "reboot"
    title = "Reboot"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect reboot indicators from system and journal."""

        uptime = self.runner.run(["uptime", "-p"])
        who = self.runner.run(["who", "-b"])
        last = self.runner.run(["last", "-x", "reboot", "-n", "5"])
        boots = self.runner.run(["journalctl", "--list-boots", "--no-pager"])
        prev_errors = self.runner.run(
            ["journalctl", "-b", "-1", "-p", "err..alert", "--no-pager", "-n", "50"],
            timeout_seconds=15,
        )
        current_reboot = self.runner.run(
            [
                "journalctl",
                "-b",
                "0",
                "--no-pager",
                "-n",
                "200",
                "-g",
                "reboot|shutdown|oom|panic|watchdog|Out of memory|kernel panic",
            ],
            timeout_seconds=15,
        )
        reboot_required = Path("/var/run/reboot-required").exists()
        reboot_pkgs = ""
        pkgs_path = Path("/var/run/reboot-required.pkgs")
        if pkgs_path.exists():
            reboot_pkgs = pkgs_path.read_text(encoding="utf-8", errors="ignore").strip()

        return {
            "uptime": uptime.to_dict(),
            "who": who.to_dict(),
            "last": last.to_dict(),
            "boots": boots.to_dict(),
            "prev_errors": prev_errors.to_dict(),
            "current_signals": current_reboot.to_dict(),
            "metrics": {
                "reboot_required": reboot_required,
                "reboot_packages": reboot_pkgs.splitlines() if reboot_pkgs else [],
                "boot_count": self._boot_count(boots.stdout),
                "prev_boot_errors": len(prev_errors.stdout.splitlines()) if prev_errors.ok else 0,
                "reboot_signals": self._count_signals(current_reboot.stdout),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build reboot analysis findings."""

        metrics = raw_data["metrics"]
        findings: list[Finding] = []

        if raw_data["uptime"]["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Uptime actuel",
                    raw_data["uptime"]["stdout"] or raw_data["who"]["stdout"],
                    "Comparer avec les incidents precedents.",
                    ["uptime -p", "who -b"],
                )
            )

        if metrics["reboot_required"]:
            pkgs = metrics["reboot_packages"]
            detail = "Reboot requis par le systeme."
            if pkgs:
                detail += " Paquets: " + ", ".join(pkgs[:5])
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Reboot en attente",
                    detail,
                    "Planifier un reboot en fenetre de maintenance.",
                    ["cat /var/run/reboot-required", "cat /var/run/reboot-required.pkgs"],
                )
            )

        if metrics["prev_boot_errors"] > 0:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Erreurs boot precedent",
                    f"{metrics['prev_boot_errors']} message(s) err/alert au boot precedent.",
                    "Analyser journalctl -b -1 pour identifier la cause du dernier reboot.",
                    ["journalctl -b -1 -p err..alert --no-pager"],
                )
            )

        causes = self._detect_reboot_causes(raw_data)
        for cause in causes:
            findings.append(cause)

        risk = self._reboot_risk_score(metrics, causes)
        findings.append(
            Finding(
                Severity.INFO if risk < 40 else Severity.WARNING if risk < 70 else Severity.CRITICAL,
                "Risque reboot estime",
                f"Score de risque reboot: {risk}% (estimation basee sur les signaux actuels).",
                "Lancer 'reboot-monitor' pour une analyse 24h detaillee.",
                ["python bin/obiora-doctor.py reboot-monitor --analyze"],
            )
        )
        return findings

    def score(self, findings: list[Finding], context: dict[str, Any]) -> int:
        """Lower score when reboot risk is high."""

        base = super().score(findings, context)
        for finding in findings:
            if finding.title == "Risque reboot estime" and "CRITICAL" in finding.details:
                return min(base, 30)
        return base

    @staticmethod
    def _boot_count(output: str) -> int:
        return len([line for line in output.splitlines() if line.strip().startswith("-")])

    @staticmethod
    def _count_signals(output: str) -> int:
        return len([line for line in output.splitlines() if line.strip()])

    @staticmethod
    def _detect_reboot_causes(raw_data: dict[str, Any]) -> list[Finding]:
        """Detect probable reboot causes from journal output."""

        findings: list[Finding] = []
        combined = (
            raw_data.get("prev_errors", {}).get("stdout", "")
            + "\n"
            + raw_data.get("current_signals", {}).get("stdout", "")
        ).lower()

        if "out of memory" in combined or "oom" in combined:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "OOM detecte",
                    "Le journal contient des signaux Out Of Memory.",
                    "Augmenter la RAM ou reduire la consommation avant prochain reboot.",
                    ["journalctl -k | grep -i oom", "dmesg | grep -i oom"],
                )
            )
        if "kernel panic" in combined or "panic" in combined:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Kernel panic detecte",
                    "Le journal mentionne un kernel panic.",
                    "Analyser dmesg et mettre a jour le kernel/drivers.",
                    ["journalctl -k | grep -i panic", "dmesg | grep -i panic"],
                )
            )
        if "watchdog" in combined:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Watchdog detecte",
                    "Un watchdog a pu provoquer un reboot force.",
                    "Verifier charge CPU et blocages kernel.",
                    ["journalctl -k | grep -i watchdog"],
                )
            )
        if raw_data["last"]["ok"] and raw_data["last"]["stdout"].strip():
            findings.append(
                Finding(
                    Severity.INFO,
                    "Historique reboot",
                    raw_data["last"]["stdout"].splitlines()[0],
                    "Verifier si le reboot etait planifie.",
                    ["last -x reboot"],
                )
            )
        return findings

    @staticmethod
    def _reboot_risk_score(metrics: dict[str, Any], causes: list[Finding]) -> int:
        """Estimate reboot probability from current signals."""

        risk = 0
        if metrics.get("reboot_required"):
            risk += 40
        if metrics.get("prev_boot_errors", 0) > 5:
            risk += 20
        for cause in causes:
            if cause.level == Severity.CRITICAL:
                risk += 25
            elif cause.level == Severity.WARNING:
                risk += 10
        return min(100, risk)
