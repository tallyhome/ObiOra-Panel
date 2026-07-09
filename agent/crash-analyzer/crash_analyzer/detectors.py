"""Détecteurs d'événements critiques — motifs kernel/dmesg réalistes."""

from __future__ import annotations

import json
import re
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from crash_analyzer.util import run_cmd
from crash_analyzer.storage import MetricsStorage


@dataclass
class DetectedEvent:
    event_type: str
    severity: str
    title: str
    details: str
    payload: dict[str, Any]


class EventDetector:
    """Analyse les métriques et journaux pour détecter les incidents."""

    # (event_type, severity, compiled_pattern, human_title)
    CRITICAL_PATTERNS: list[tuple[str, str, re.Pattern[str], str]] = [
        (
            "kernel_panic",
            "critical",
            re.compile(
                r"Kernel panic - not syncing|kernel panic|Oops:|BUG: unable to handle",
                re.I,
            ),
            "Kernel panic",
        ),
        (
            "soft_lockup",
            "critical",
            re.compile(r"watchdog.*soft lockup|NMI watchdog.*CPU.*stuck|soft lockup", re.I),
            "Soft lockup CPU",
        ),
        (
            "hard_lockup",
            "critical",
            re.compile(r"hard LOCKUP|hard lockup|NMI watchdog.*hard lockup", re.I),
            "Hard lockup CPU",
        ),
        (
            "rcu_stall",
            "critical",
            re.compile(r"rcu_.*detected stalls|RCU stall|rcu_sched detected stalls", re.I),
            "RCU stall",
        ),
        (
            "oom_killer",
            "critical",
            re.compile(
                r"Out of memory: Kill process|Killed process \d+|oom-kill:|invoked oom-killer|Memory cgroup out of memory",
                re.I,
            ),
            "OOM Killer",
        ),
        (
            "watchdog",
            "critical",
            re.compile(r"watchdog: BUG|Watchdog detected hard LOCKUP|Hung task", re.I),
            "Watchdog timeout",
        ),
        (
            "nvme_error",
            "critical",
            re.compile(
                r"nvme\d+n\d+.*(I/O error|critical warning|reset controller failed|Abort status|media error)",
                re.I,
            ),
            "Erreur NVMe",
        ),
        (
            "raid_error",
            "critical",
            re.compile(
                r"md\d+.*(fail|degraded|Device failure)|raid.*degraded|blk_update_request: I/O error.*md",
                re.I,
            ),
            "Erreur RAID",
        ),
        (
            "smart_error",
            "warning",
            re.compile(
                r"SMART.*(FAIL|Prefail|error)|Reallocated_Sector_Ct|Current_Pending_Sector|Offline uncorrectable",
                re.I,
            ),
            "Alerte SMART",
        ),
        (
            "ecc_error",
            "critical",
            re.compile(
                r"EDAC.*(UE|CE|error)|Machine check events|mce:.*Hardware error|Memory failure|DIMM failure|MCA:",
                re.I,
            ),
            "Erreur ECC / Machine Check",
        ),
        (
            "filesystem_ro",
            "critical",
            re.compile(
                r"Remounting filesystem read-only|switching to read-only|Buffer I/O error on dev|EXT4-fs error|XFS.*Corruption|I/O error.*block",
                re.I,
            ),
            "Filesystem en lecture seule",
        ),
        (
            "virtualizor_crash",
            "critical",
            re.compile(
                r"(virtqemud|libvirtd|qemu-system).*(segfault|fatal|crash|core dumped)|virtualizor.*(crash|fatal)",
                re.I,
            ),
            "Crash Virtualizor/QEMU",
        ),
        (
            "network_loss",
            "warning",
            re.compile(
                r"link down|NIC Link is Down|network is unreachable|bond\d+.*(down|failed)|eno\d+.*Link is Down",
                re.I,
            ),
            "Perte réseau",
        ),
        (
            "io_error",
            "critical",
            re.compile(r"blk_update_request: I/O error|Buffer I/O error|end_request: I/O error", re.I),
            "Erreur I/O disque",
        ),
        (
            "segfault",
            "warning",
            re.compile(r"segfault at|traps:.*general protection fault", re.I),
            "Segfault processus",
        ),
    ]

    def __init__(self, state_file: str) -> None:
        self.state_file = Path(state_file)
        self.state_file.parent.mkdir(parents=True, exist_ok=True)
        self._state = self._load_state()
        self._seen_signatures: set[str] = set(self._state.get("seen_signatures", []))
        self._metric_alert_cooldown: dict[str, float] = {}

    def _load_state(self) -> dict[str, Any]:
        if self.state_file.is_file():
            try:
                return json.loads(self.state_file.read_text(encoding="utf-8"))
            except json.JSONDecodeError:
                pass
        return {"last_boot_id": "", "last_uptime": 0, "graceful_shutdown": False, "seen_signatures": []}

    def save_state(self) -> None:
        self._state["seen_signatures"] = list(self._seen_signatures)[-500:]
        self.state_file.write_text(json.dumps(self._state, indent=2), encoding="utf-8")

    def mark_graceful_shutdown(self) -> None:
        self._state["graceful_shutdown"] = True
        self.save_state()

    def check_unexpected_reboot(self, boot_id: str, uptime_seconds: float) -> DetectedEvent | None:
        """Détecte un redémarrage non planifié au démarrage du daemon."""
        prev_boot = self._state.get("last_boot_id", "")
        graceful = self._state.get("graceful_shutdown", False)
        if prev_boot and boot_id != prev_boot and uptime_seconds < 600 and not graceful:
            return DetectedEvent(
                event_type="unexpected_reboot",
                severity="critical",
                title="Redémarrage inattendu détecté",
                details=f"Boot ID précédent: {prev_boot}, actuel: {boot_id}",
                payload={"previous_boot_id": prev_boot, "boot_id": boot_id, "uptime_seconds": uptime_seconds},
            )
        self._state["last_boot_id"] = boot_id
        self._state["graceful_shutdown"] = False
        self._state["last_uptime"] = uptime_seconds
        self.save_state()
        return None

    def scan_logs(self) -> list[DetectedEvent]:
        """Analyse journalctl et dmesg pour motifs critiques."""
        events: list[DetectedEvent] = []
        sources = [
            ("journalctl", run_cmd(["journalctl", "-k", "-n", "150", "--no-pager", "-o", "short-precise"], timeout=5)),
            ("journalctl_err", run_cmd(["journalctl", "-p", "err..emerg", "-n", "50", "--no-pager", "-o", "short-precise"], timeout=4)),
            ("dmesg", run_cmd(["dmesg", "-T", "-l", "err,crit,alert,emerg,warn"], timeout=4)),
        ]
        for source, content in sources:
            if not content:
                continue
            for event_type, severity, pattern, title in self.CRITICAL_PATTERNS:
                for match in pattern.finditer(content):
                    line = self._extract_line(content, match.start())
                    signature = f"{event_type}:{hash(line)}"
                    if signature in self._seen_signatures:
                        continue
                    self._seen_signatures.add(signature)
                    events.append(DetectedEvent(
                        event_type=event_type,
                        severity=severity,
                        title=title,
                        details=line[:500],
                        payload={"source": source, "matched": match.group(0)[:200]},
                    ))
        return events

    def scan_metrics(self, metrics: dict[str, dict[str, Any]]) -> list[DetectedEvent]:
        """Analyse les métriques collectées pour anomalies."""
        events: list[DetectedEvent] = []
        now = time.time()

        edac = metrics.get("edac", {})
        ue = edac.get("ue_count", 0)
        if isinstance(ue, int) and ue > 0 and self._cooldown_ok("ecc_ue", now, 300):
            events.append(DetectedEvent(
                event_type="ecc_error",
                severity="critical",
                title="Erreur ECC (UE)",
                details=f"UE count: {ue}",
                payload=edac,
            ))

        memory = metrics.get("memory", {})
        mem_pct = memory.get("used_percent", 0)
        if isinstance(mem_pct, (int, float)) and mem_pct > 98 and self._cooldown_ok("memory_pressure", now, 120):
            events.append(DetectedEvent(
                event_type="memory_pressure",
                severity="warning",
                title="Pression mémoire extrême",
                details=f"RAM utilisée: {mem_pct}%",
                payload=memory,
            ))

        systemd = metrics.get("systemd", {})
        failed = systemd.get("failed_count", 0)
        if isinstance(failed, int) and failed > 0 and self._cooldown_ok("systemd_failed", now, 300):
            events.append(DetectedEvent(
                event_type="systemd_failed",
                severity="warning",
                title="Services systemd en échec",
                details=", ".join(systemd.get("failed_units", [])[:5]),
                payload=systemd,
            ))

        return events

    def _cooldown_ok(self, key: str, now: float, seconds: int) -> bool:
        last = self._metric_alert_cooldown.get(key, 0)
        if now - last < seconds:
            return False
        self._metric_alert_cooldown[key] = now
        return True

    @staticmethod
    def _extract_line(content: str, pos: int) -> str:
        start = content.rfind("\n", 0, pos) + 1
        end = content.find("\n", pos)
        if end == -1:
            end = len(content)
        return content[start:end].strip()

    def persist_events(self, storage: MetricsStorage, events: list[DetectedEvent]) -> None:
        for event in events:
            storage.insert_event(
                event.event_type,
                event.severity,
                event.title,
                event.details,
                event.payload,
            )
