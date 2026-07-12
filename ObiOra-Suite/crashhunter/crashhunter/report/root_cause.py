"""Weighted root-cause analysis — storage I/O stall vs driver crash vs network driver."""

from __future__ import annotations

import re
from typing import Any

# (regex, score, evidence label template — device extracted from match when possible)
STORAGE_SIGNATURES: list[tuple[str, int, str]] = [
    (r"xfsaild/\S+.*\bD\b|xfsaild/\S+\s+state:D", 25, "xfsaild en état D"),
    (r"blkdev_issue_flush|blkdev_fsync", 20, "blkdev flush/fsync bloqué"),
    (r"flush-\S+|workqueue.*flush", 15, "workqueue flush bloquée"),
    (r"xfs-cil/\S+|xlog_cil_push_work|xlog_write", 15, "journal XFS CIL bloqué"),
    (r"xfs_log_worker|xfs-sync/\S+|xfs_buf_ioend_work|xfs_end_io", 12, "workers XFS en attente"),
    (r"xfs_file_fsync|xfsaild/\S+", 10, "fsync XFS"),
    (r"io_schedule|io_schedule_timeout|rq_qos_wait", 10, "scheduler I/O bloqué"),
    (r"writeback|wb_workfn", 8, "writeback bloqué"),
    (r"blk_mq_timeout_work|blk_mq_requeue_work|blk_mq.*timeout", 12, "blk-mq timeout"),
    (r"task \S+ blocked for more than|hung task", 10, "tâche bloquée (hung task)"),
    (r"journald.*fsync|journal_file_rotate.*fsync|systemd-journald.*D\b", 10, "journald bloqué dans fsync"),
    (r"state:D|\sD\s+\d", 5, "processus en D-state"),
]

NETWORK_DRIVER_SIGNATURES: list[tuple[str, int, str]] = [
    (r"NETDEV WATCHDOG", 30, "NETDEV WATCHDOG"),
    (r"(ixgbe|i40e|ice|mlx5|bnxt).*reset|resetting.*(ixgbe|i40e|ice)", 25, "reset driver réseau"),
    (r"transmit timed out|tx timeout", 20, "TX timeout NIC"),
]

RCU_PATTERN = re.compile(r"rcu stall|RCU stall|rcu_preempt detected stalls", re.IGNORECASE)
DRIVER_CRASH_PATTERN = re.compile(r"\bBUG:|\bOops:", re.IGNORECASE)
NVME_PATTERN = re.compile(r"nvme.*timeout|nvme.*reset|reset.*controller", re.IGNORECASE)

SECONDARY_EVENT_MAP: dict[str, str] = {
    "rcu_stall": "RCU stall",
    "clock_drift": "clock drift (legacy)",
    "collector_gap": "collector gap",
    "clock_adjustment": "correction d'horloge",
    "journald_watchdog": "journald watchdog timeout",
    "ssh_timeout": "SSH timeout",
    "virtualizor_timeout": "Virtualizor timeout",
    "virsh_timeout": "virsh timeout",
    "virsh_slow": "virsh lent",
    "ping_timeout": "ping timeout",
    "iowait_high": "IOWait élevé",
    "watchdog_warning": "watchdog warning",
    "hung_task": "hung task",
}


class RootCauseAnalyzer:
    """Correlate corpus + timeline triggers into primary/secondary causes with evidence."""

    def analyze(
        self,
        corpus: list[str],
        *,
        events: list[dict[str, Any]] | None = None,
        triggers: list[str] | None = None,
        existing_findings: list[dict[str, Any]] | None = None,
    ) -> dict[str, Any] | None:
        events = events or []
        triggers = triggers or []
        text = "\n".join(corpus)
        evidence: list[str] = []
        storage_score = 0
        network_score = 0
        devices: set[str] = set()

        for pattern, score, label in STORAGE_SIGNATURES:
            for match in re.finditer(pattern, text, re.IGNORECASE):
                storage_score += score
                line = match.group(0).strip()[:200]
                dev = self._extract_device(line)
                if dev:
                    devices.add(dev)
                evidence.append(f"{label}: {line}" if line else label)

        for pattern, score, label in NETWORK_DRIVER_SIGNATURES:
            if re.search(pattern, text, re.IGNORECASE):
                network_score += score
                evidence.append(label)

        if NVME_PATTERN.search(text):
            storage_score += 20
            evidence.append("NVMe timeout/reset détecté")

        rcu_in_corpus = bool(RCU_PATTERN.search(text))
        driver_crash_in_corpus = bool(DRIVER_CRASH_PATTERN.search(text))

        storage_event_ts = self._first_event_index(events, {
            "iowait_high", "iowait_increased", "d_state_processes", "qemu_storage_wait",
            "disk_latency_spike", "storage_io_stall",
        })
        rcu_event_ts = self._first_event_index(events, {"rcu_stall", "soft_lockup", "hard_lockup", "hung_task"})

        if "iowait_high" in triggers or "d_state_processes" in triggers:
            storage_score += 10
        if rcu_in_corpus and storage_score > 0:
            if rcu_event_ts is None or storage_event_ts is None or storage_event_ts <= rcu_event_ts:
                storage_score += 5
                evidence.append("RCU stall corrélé après symptômes I/O (effet secondaire probable)")

        secondary: list[str] = []
        if rcu_in_corpus or "rcu_stall" in triggers:
            secondary.append("rcu_stall")
        for trig in triggers:
            if trig in SECONDARY_EVENT_MAP and trig not in secondary:
                if storage_score >= 40 and trig in ("rcu_stall", "clock_drift", "collector_gap"):
                    secondary.append(trig)
                elif storage_score < 40:
                    secondary.append(trig)
        if re.search(r"journald.*watchdog|watchdog.*journald", text, re.IGNORECASE):
            secondary.append("journald_watchdog")

        secondary_labels = [SECONDARY_EVENT_MAP.get(s, s.replace("_", " ")) for s in secondary]

        # Network driver takes priority when strong network signatures (TEST C)
        if network_score >= 40:
            return self._result(
                category="network_driver",
                title="Network Driver Crash",
                title_fr="Panne driver réseau",
                confidence=min(0.99, 0.75 + network_score / 100),
                description="Reset ou watchdog du driver réseau — panne NIC probable.",
                evidence=evidence[:20],
                secondary_effects=secondary_labels,
                device=None,
            )

        # Storage I/O stall (TEST B, D)
        if storage_score >= 40:
            device = next(iter(devices), None)
            desc = "Blocage prolongé du chemin d'écriture"
            if device:
                desc += f" XFS / block layer sur {device}."
            else:
                desc += " XFS / block layer."
            return self._result(
                category="storage_io_stall",
                title="Storage I/O Stall",
                title_fr="Blocage I/O stockage",
                confidence=min(0.99, 0.70 + storage_score / 100),
                description=desc,
                evidence=list(dict.fromkeys(evidence))[:20],
                secondary_effects=secondary_labels,
                device=device,
            )

        # RCU alone — moderate driver/kernel stall, NOT storage (TEST A)
        if rcu_in_corpus and not driver_crash_in_corpus and storage_score < 25:
            return self._result(
                category="rcu_stall",
                title="Rcu Stall",
                title_fr="RCU stall",
                confidence=0.72,
                description="RCU stall détecté sans signature I/O/block layer dominante.",
                evidence=[e for e in evidence if "RCU" in e.upper()] or ["RCU stall dans les logs kernel"],
                secondary_effects=secondary_labels,
                device=None,
            )

        if driver_crash_in_corpus and storage_score < 30:
            return self._result(
                category="driver_crash",
                title="Driver Crash",
                title_fr="Driver crash",
                confidence=0.86,
                description="Oops/BUG kernel — crash driver ou sous-système noyau.",
                evidence=[line[:200] for line in corpus if DRIVER_CRASH_PATTERN.search(line)][:10],
                secondary_effects=secondary_labels,
                device=None,
            )

        if existing_findings and storage_score >= 25:
            top = existing_findings[0]
            if top.get("category") == "driver_crash" and not driver_crash_in_corpus:
                return self._result(
                    category="storage_io_stall",
                    title="Storage I/O Stall",
                    title_fr="Blocage I/O stockage",
                    confidence=min(0.95, 0.65 + storage_score / 100),
                    description="Signatures I/O/block layer — reclassification depuis driver_crash (Call Trace seul).",
                    evidence=list(dict.fromkeys(evidence))[:20],
                    secondary_effects=secondary_labels,
                    device=next(iter(devices), None),
                )

        return None

    @staticmethod
    def _result(
        *,
        category: str,
        title: str,
        title_fr: str,
        confidence: float,
        description: str,
        evidence: list[str],
        secondary_effects: list[str],
        device: str | None,
    ) -> dict[str, Any]:
        return {
            "primary_cause": {
                "category": category,
                "title": title,
                "title_fr": title_fr,
                "confidence": round(confidence, 2),
                "description": description,
                "device": device,
            },
            "secondary_effects": secondary_effects,
            "evidence": evidence,
        }

    @staticmethod
    def _extract_device(line: str) -> str | None:
        for pattern in (r"xfsaild/(\S+)", r"xfs-sync/(\S+)", r"xfs-cil/(\S+)", r"/dev/(\S+)"):
            match = re.search(pattern, line, re.IGNORECASE)
            if match:
                return match.group(1).rstrip(")")
        return None

    @staticmethod
    def _first_event_index(events: list[dict[str, Any]], names: set[str]) -> int | None:
        for idx, entry in enumerate(events):
            if entry.get("event") in names:
                return idx
        return None
