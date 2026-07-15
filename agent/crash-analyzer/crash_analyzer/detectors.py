"""Détecteurs d'événements critiques — motifs kernel/dmesg réalistes."""

from __future__ import annotations

import hashlib
import json
import re
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from crash_analyzer.util import run_cmd
from crash_analyzer.storage import MetricsStorage

# Enregistrement / init EDAC — pas une erreur matérielle (cf. doc kernel EDAC)
EDAC_INIT_NOISE = re.compile(
    r"Giving out device|Successfully registered|registered with EDAC|"
    r"EDAC MC\d+:\s*(?:Giving|Registering|Allocated|created)",
    re.I,
)

_TS_PREFIX = re.compile(r"^\[[^\]]+\]\s*")
_OOM_KILL = re.compile(r"Killed process\s+(\d+)\s+\(([^)]+)\)", re.I)

# Cooldown entre deux alertes identiques (secondes)
LOG_EVENT_COOLDOWN_SECONDS: dict[str, int] = {
    "oom_killer": 3600,
    "kernel_panic": 86400,
    "unexpected_reboot": 86400,
}
DEFAULT_LOG_EVENT_COOLDOWN = 1800

# Erreurs ECC/MCE réelles — compteurs CE/UE, pas sous-chaîne dans "device"
ECC_ERROR_PATTERNS: list[re.Pattern[str]] = [
    re.compile(r"EDAC MC\d+:\s+\d+\s+(?:UE|CE)\b", re.I),
    re.compile(r"EDAC.*\b(?:uncorrected|corrected)\s+error\b", re.I),
    re.compile(r"Machine check events logged", re.I),
    re.compile(r"mce:\s*.*Hardware error", re.I),
    re.compile(r"Memory failure(?:\s+on|\s+at|\s+from|\s+in)", re.I),
    re.compile(r"DIMM failure", re.I),
    re.compile(r"MCA:\s*(?:Bank|Fatal|Uncorrected|Machine check)", re.I),
]


def is_edac_controller_init(line: str) -> bool:
    """True si la ligne EDAC est une init contrôleur, pas un événement d'erreur."""
    return bool(EDAC_INIT_NOISE.search(line))


def matches_ecc_error(line: str) -> bool:
    """Détecte une vraie erreur ECC/MCE/EDAC (exclut l'enregistrement skx_edac, etc.)."""
    if is_edac_controller_init(line):
        return False
    return any(pattern.search(line) for pattern in ECC_ERROR_PATTERNS)


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
        self._log_event_cooldown: dict[str, float] = {
            str(k): float(v) for k, v in (self._state.get("log_event_cooldown") or {}).items()
        }
        self._metric_alert_cooldown: dict[str, float] = {}

    def _load_state(self) -> dict[str, Any]:
        if self.state_file.is_file():
            try:
                return json.loads(self.state_file.read_text(encoding="utf-8"))
            except json.JSONDecodeError:
                pass
        return {"last_boot_id": "", "last_uptime": 0, "graceful_shutdown": False, "seen_signatures": [], "log_event_cooldown": {}}

    def save_state(self) -> None:
        self._state["seen_signatures"] = list(self._seen_signatures)[-500:]
        cutoff = time.time() - 86400
        self._state["log_event_cooldown"] = {
            k: v for k, v in self._log_event_cooldown.items() if v >= cutoff
        }
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
        seen_this_scan: set[str] = set()
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
                    signature = self._event_signature(event_type, line)
                    if signature in seen_this_scan:
                        continue
                    if not self._log_event_allowed(signature, event_type):
                        continue
                    seen_this_scan.add(signature)
                    events.append(DetectedEvent(
                        event_type=event_type,
                        severity=severity,
                        title=title,
                        details=line[:500],
                        payload={"source": source, "matched": match.group(0)[:200], "signature": signature},
                    ))
            for line in content.splitlines():
                stripped = line.strip()
                if not stripped or not matches_ecc_error(stripped):
                    continue
                signature = self._event_signature("ecc_error", stripped)
                if signature in seen_this_scan:
                    continue
                if not self._log_event_allowed(signature, "ecc_error"):
                    continue
                seen_this_scan.add(signature)
                events.append(DetectedEvent(
                    event_type="ecc_error",
                    severity="critical",
                    title="Erreur ECC / Machine Check",
                    details=stripped[:500],
                    payload={"source": source, "matched": "ecc_error_pattern", "signature": signature},
                ))
        if events:
            self.save_state()
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

    def _log_event_allowed(self, signature: str, event_type: str) -> bool:
        now = time.time()
        cooldown = LOG_EVENT_COOLDOWN_SECONDS.get(event_type, DEFAULT_LOG_EVENT_COOLDOWN)
        last = self._log_event_cooldown.get(signature, 0)
        if now - last < cooldown:
            return False
        self._log_event_cooldown[signature] = now
        self._seen_signatures.add(signature)
        return True

    @staticmethod
    def _normalize_line_for_signature(line: str) -> str:
        normalized = _TS_PREFIX.sub("", line.strip())
        return re.sub(r"\s+", " ", normalized)

    @classmethod
    def _event_signature(cls, event_type: str, line: str) -> str:
        normalized = cls._normalize_line_for_signature(line)
        if event_type == "oom_killer":
            match = _OOM_KILL.search(normalized)
            if match:
                return f"{event_type}:pid={match.group(1)}:proc={match.group(2).lower()}"
        digest = hashlib.sha256(normalized.encode("utf-8")).hexdigest()[:16]
        return f"{event_type}:{digest}"

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
