"""Optional Lynis hardening audit when installed."""

from __future__ import annotations

import re
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class LynisModule(DiagnosticModule):
    """Run Lynis audit if available (5-15 min)."""

    name = "lynis"
    title = "Lynis"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        which = self.runner.run(["which", "lynis"])
        if which.missing or not which.stdout.strip():
            return {"metrics": {"available": False}}

        result = self.runner.run(
            ["lynis", "audit", "system", "--quick", "--quiet"],
            timeout_seconds=300,
        )
        output = result.stdout + result.stderr
        score_match = re.search(r"Hardening index\s*:\s*\[\s*(\d+(?:\.\d+)?)", output)
        warnings = len(re.findall(r"\[\s*warning\s*\]", output, re.I))
        suggestions = len(re.findall(r"\[\s*suggestion\s*\]", output, re.I))

        return {
            "metrics": {
                "available": True,
                "hardening_index": float(score_match.group(1)) if score_match else None,
                "warnings": warnings,
                "suggestions": suggestions,
                "output_sample": output[-1500:] if output else "",
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        if not m.get("available"):
            return [
                Finding(
                    Severity.INFO,
                    "Lynis non installe",
                    "Audit Lynis optionnel non disponible.",
                    "Installer lynis pour un audit CIS-like complet.",
                    ["dnf install lynis", "apt install lynis"],
                )
            ]

        findings: list[Finding] = []
        idx = m.get("hardening_index")
        if idx is not None and idx < 60:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Score Lynis faible",
                    f"Hardening index: {idx}/100, {m['warnings']} warning(s).",
                    "Consulter /var/log/lynis.log et appliquer les suggestions.",
                    ["lynis audit system"],
                )
            )
        elif idx is not None:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Score Lynis acceptable",
                    f"Hardening index: {idx}/100.",
                    "Re-auditer apres chaque changement majeur.",
                )
            )
        return findings or [
            Finding(Severity.INFO, "Lynis execute", "Audit termine.", "Voir le rapport Lynis complet.")
        ]
