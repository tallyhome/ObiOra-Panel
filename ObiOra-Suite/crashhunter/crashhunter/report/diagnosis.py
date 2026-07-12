"""Diagnosis engine — pattern matching for freeze root causes."""

from __future__ import annotations

import re
from typing import Any

from crashhunter.report.root_cause import RootCauseAnalyzer
from crashhunter.utils.types import DiagnosisFinding, SuspiciousEvent

PATTERNS: list[tuple[str, str, float, str]] = [
    (r"kernel panic|Kernel panic", "kernel_panic", 0.98, "critical"),
    (r"rcu stall|RCU stall|rcu_preempt detected stalls", "rcu_stall", 0.95, "critical"),
    (r"soft lockup|Soft lockup", "soft_lockup", 0.94, "critical"),
    (r"hard lockup|Hard lockup", "hard_lockup", 0.96, "critical"),
    (r"hung task|blocked for more than", "hung_task", 0.92, "critical"),
    (r"watchdog: BUG|Watchdog detected", "watchdog", 0.93, "critical"),
    (r"Out of memory|Killed process|oom-kill", "oom", 0.90, "high"),
    (r"segfault|segmentation fault", "segfault", 0.75, "high"),
    (r"I/O error|blk_update_request|Buffer I/O error", "disk_timeout", 0.88, "high"),
    (r"nvme.*reset|nvme.*timeout", "nvme_reset", 0.87, "high"),
    (r"SMART.*failing|Predictive Failure", "smart_degradation", 0.80, "high"),
    (r"filesystem corruption|XFS.*corrupt|EXT4-fs error", "filesystem_corruption", 0.85, "critical"),
    (r"Machine check|MCE|hardware error|EDAC", "hardware_error", 0.91, "critical"),
    (r"PCIe.*error|AER.*error", "pcie_error", 0.82, "high"),
    (r"\bBUG:|\bOops:", "driver_crash", 0.86, "critical"),
    (r"NETDEV WATCHDOG|transmit timed out", "network_driver", 0.88, "critical"),
    (r"xfsaild/\S+.*\bD\b|blkdev_issue_flush|xlog_cil_push_work|xfs_buf_ioend_work", "storage_io_stall", 0.82, "critical"),
    (r"libvirt|virsh|qemu.*error", "virtualizor_libvirt", 0.70, "medium"),
    (r"network is unreachable|link down|NIC.*down", "network_freeze", 0.65, "medium"),
    (r"interrupt storm|IRQ.*storm", "interrupt_storm", 0.78, "high"),
    (r"thermal|temperature.*critical|CPU.*overheat", "thermal_event", 0.83, "high"),
    (r"power loss|AC lost|PSU", "power_loss", 0.80, "high"),
    (r"systemd.*reboot|Rebooting", "systemd_reboot", 0.60, "medium"),
    (r"resetting device|usb .* disconnect", "usb_reset", 0.55, "low"),
]


class DiagnosisEngine:
    """Analyze correlated black box data and produce root cause hypotheses."""

    def __init__(self) -> None:
        self._root_cause = RootCauseAnalyzer()

    def analyze(
        self,
        correlation: dict[str, Any],
        *,
        events: list[dict[str, Any]] | None = None,
        triggers: list[str] | None = None,
    ) -> dict[str, Any]:
        corpus = self._build_corpus(correlation)
        findings: list[DiagnosisFinding] = []
        seen_categories: set[str] = set()

        for pattern, category, base_confidence, severity in PATTERNS:
            matches = [line for line in corpus if re.search(pattern, line, re.IGNORECASE)]
            if matches and category not in seen_categories:
                seen_categories.add(category)
                findings.append(
                    {
                        "category": category,
                        "title": self._title_for(category),
                        "description": self._description_for(category),
                        "confidence": round(min(0.99, base_confidence + len(matches) * 0.01), 2),
                        "evidence": matches[:10],
                        "severity": severity,
                    }
                )

        findings.sort(key=lambda f: f["confidence"], reverse=True)
        suspicious = correlation.get("top_suspicious_events", [])

        root = self._root_cause.analyze(
            corpus,
            events=events,
            triggers=triggers,
            existing_findings=findings,
        )

        if root:
            primary = root["primary_cause"]
            cat = primary["category"]
            findings = [f for f in findings if f["category"] != cat or f["category"] == "driver_crash"]
            findings.insert(0, {
                "category": cat,
                "title": primary["title"],
                "description": primary["description"],
                "confidence": primary["confidence"],
                "evidence": root.get("evidence", [])[:10],
                "severity": "critical",
            })
            findings.sort(key=lambda f: f["confidence"], reverse=True)

        if not findings:
            return {
                "verdict": "UNKNOWN FREEZE",
                "confidence": 0.0,
                "findings": [],
                "top_suspicious_events": suspicious[:20],
                "summary": (
                    "Aucune cause évidente identifiée. "
                    "Consultez les 20 événements suspects corrélés par le Black Box."
                ),
            }

        primary_finding = findings[0]
        result: dict[str, Any] = {
            "verdict": primary_finding["title"],
            "verdict_fr": root["primary_cause"]["title_fr"] if root else self._title_fr(primary_finding["category"]),
            "confidence": primary_finding["confidence"],
            "findings": findings,
            "top_suspicious_events": suspicious[:20],
            "summary": self._build_summary(primary_finding, suspicious, root),
        }

        if root:
            result["primary_cause"] = root["primary_cause"]
            result["secondary_effects"] = root.get("secondary_effects", [])
            result["evidence"] = root.get("evidence", [])

        return result

    def _build_corpus(self, correlation: dict[str, Any]) -> list[str]:
        lines: list[str] = []
        lines.extend(correlation.get("kernel_events", []))
        lines.extend(correlation.get("systemd_events", []))
        lines.extend(correlation.get("vm_events", []))
        for snap in correlation.get("last_snapshots", []):
            for line in snap.get("kernel", {}).get("dmesg_tail", []):
                lines.append(line)
            for line in snap.get("kernel", {}).get("journal_tail", []):
                lines.append(line)
            dstate = snap.get("dstate", {})
            for proc in dstate.get("processes", []):
                comm = proc.get("comm", "")
                wchan = proc.get("wchan", "")
                state = proc.get("state", "")
                lines.append(f"{comm} state:{state} wchan={wchan}")
        return lines

    @staticmethod
    def _title_for(category: str) -> str:
        titles = {
            "storage_io_stall": "Storage I/O Stall",
            "network_driver": "Network Driver Crash",
            "rcu_stall": "Rcu Stall",
            "driver_crash": "Driver Crash",
        }
        return titles.get(category, category.replace("_", " ").title())

    @staticmethod
    def _title_fr(category: str) -> str:
        titles = {
            "storage_io_stall": "Blocage I/O stockage",
            "network_driver": "Panne driver réseau",
            "rcu_stall": "RCU stall",
            "driver_crash": "Crash driver",
        }
        return titles.get(category, category.replace("_", " "))

    @staticmethod
    def _description_for(category: str) -> str:
        descriptions = {
            "kernel_panic": "Le noyau a paniqué — arrêt brutal du système.",
            "rcu_stall": "RCU stall détecté — le noyau n'a pas pu terminer une phase RCU.",
            "soft_lockup": "Soft lockup — CPU bloqué en mode noyau sans préemption.",
            "hard_lockup": "Hard lockup — CPU totalement figé, watchdog déclenché.",
            "hung_task": "Tâche bloquée (hung task) — processus en attente I/O ou verrou.",
            "watchdog": "Watchdog matériel ou logiciel a forcé un redémarrage.",
            "oom": "OOM killer — mémoire épuisée, processus tués.",
            "disk_timeout": "Timeout disque — I/O bloquée ou média défaillant.",
            "storage_io_stall": "Blocage prolongé du chemin I/O stockage / block layer / filesystem.",
            "nvme_reset": "NVMe reset — contrôleur SSD a été réinitialisé.",
            "smart_degradation": "SMART signale une dégradation du disque.",
            "filesystem_corruption": "Corruption ou erreur filesystem détectée.",
            "hardware_error": "Erreur matérielle (MCE/EDAC/RAS).",
            "driver_crash": "Oops/BUG kernel — crash driver ou sous-système noyau.",
            "network_driver": "Watchdog ou reset du driver réseau.",
            "virtualizor_libvirt": "Problème Virtualizor/libvirt/QEMU.",
            "thermal_event": "Événement thermique — surchauffe CPU/composant.",
            "power_loss": "Perte d'alimentation ou instabilité PSU.",
            "interrupt_storm": "Tempête d'interruptions — surcharge IRQ.",
        }
        return descriptions.get(category, f"Anomalie détectée: {category}")

    @staticmethod
    def _build_summary(
        primary: DiagnosisFinding,
        suspicious: list[SuspiciousEvent],
        root: dict[str, Any] | None,
    ) -> str:
        parts = [
            f"Cause probable: {primary.get('description', primary['title'])} (confiance {primary['confidence']:.0%}).",
        ]
        if root and root.get("secondary_effects"):
            parts.append(
                "Effets secondaires: " + ", ".join(root["secondary_effects"][:8]) + "."
            )
        if suspicious:
            parts.append(f"{len(suspicious)} événements suspects corrélés dans les 60 dernières minutes.")
        return " ".join(parts)
