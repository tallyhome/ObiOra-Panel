"""Crash similarity engine — compare incidents across history."""

from __future__ import annotations

import hashlib
import json
import logging
from pathlib import Path
from typing import Any

from crashhunter.config.settings import Settings

logger = logging.getLogger("crashhunter.similarity")


class SimilarityEngine:
    """Fingerprint crashes and find similar past incidents."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.index_path = settings.similarity_index_file

    def fingerprint(self, report: dict[str, Any]) -> dict[str, Any]:
        """Build a fingerprint from diagnosis, triggers, and pre-crash profile."""
        diagnosis = report.get("diagnosis", {})
        blackbox = report.get("blackbox", {})
        incident = report.get("incident", {})
        triggers = incident.get("triggers", []) or [
            f.get("category", "") for f in diagnosis.get("findings", [])
        ]
        categories = sorted(set(triggers + [f.get("category", "") for f in diagnosis.get("findings", [])]))
        timeline = blackbox.get("timeline", [])
        profile = self._metric_profile(timeline)
        events = [
            e.get("event", "") for e in blackbox.get("top_suspicious_events", [])
        ]
        signature = "|".join(categories + events[:10])
        return {
            "report_id": report.get("report_id", ""),
            "categories": categories,
            "triggers": triggers,
            "events": events,
            "metric_profile": profile,
            "hash": hashlib.sha256(signature.encode()).hexdigest()[:16],
        }

    def index_report(self, report: dict[str, Any]) -> None:
        if not self.settings.similarity.enabled:
            return
        fp = self.fingerprint(report)
        index = self._load_index()
        index[fp["report_id"]] = fp
        self._save_index(index)

    def find_similar(self, report: dict[str, Any]) -> list[dict[str, Any]]:
        if not self.settings.similarity.enabled:
            return []
        current = self.fingerprint(report)
        index = self._load_index()
        results: list[dict[str, Any]] = []
        for report_id, fp in index.items():
            if report_id == current["report_id"]:
                continue
            score = self._similarity_score(current, fp)
            if score >= self.settings.similarity.min_confidence:
                results.append({
                    "report_id": report_id,
                    "confidence": round(score, 2),
                    "shared_categories": list(set(current["categories"]) & set(fp["categories"])),
                    "probable_root_cause": self._infer_root_cause(fp),
                })
        results.sort(key=lambda r: r["confidence"], reverse=True)
        return results[:10]

    def _similarity_score(self, a: dict[str, Any], b: dict[str, Any]) -> float:
        cat_a = set(a.get("categories", []))
        cat_b = set(b.get("categories", []))
        evt_a = set(a.get("events", []))
        evt_b = set(b.get("events", []))
        cat_jaccard = len(cat_a & cat_b) / max(len(cat_a | cat_b), 1)
        evt_jaccard = len(evt_a & evt_b) / max(len(evt_a | evt_b), 1)
        metric_dist = self._metric_distance(a.get("metric_profile", {}), b.get("metric_profile", {}))
        return round(cat_jaccard * 0.4 + evt_jaccard * 0.3 + (1 - metric_dist) * 0.3, 2)

    @staticmethod
    def _metric_profile(timeline: list[dict[str, Any]]) -> dict[str, float]:
        if not timeline:
            return {}
        cpus = [e.get("cpu_percent", 0) for e in timeline]
        blocked = [e.get("blocked_tasks", 0) for e in timeline]
        return {
            "cpu_avg": sum(cpus) / len(cpus),
            "cpu_max": max(cpus),
            "blocked_avg": sum(blocked) / len(blocked),
            "blocked_max": max(blocked),
        }

    @staticmethod
    def _metric_distance(a: dict[str, float], b: dict[str, float]) -> float:
        if not a or not b:
            return 1.0
        keys = set(a) & set(b)
        if not keys:
            return 1.0
        diffs = [abs(a[k] - b[k]) / max(abs(a[k]), abs(b[k]), 1) for k in keys]
        return sum(diffs) / len(diffs)

    @staticmethod
    def _infer_root_cause(fp: dict[str, Any]) -> str:
        mapping = {
            "disk_timeout": "Storage controller",
            "nvme_reset": "NVMe controller",
            "storage_io_stall": "Storage I/O stall",
            "network_driver": "Network driver",
            "d_state_processes": "Storage I/O blockage",
            "iowait_high": "Disk subsystem",
            "virtualizor_timeout": "Virtualizor",
            "libvirt_timeout": "libvirt",
            "qemu_not_progressing": "QEMU/KVM",
            "scheduler_stall": "Kernel scheduler",
            "ssh_timeout": "System freeze",
            "ping_timeout": "Network freeze",
            "thermal_event": "Thermal event",
            "hardware_error": "Hardware",
        }
        for cat in fp.get("categories", []):
            if cat in mapping:
                return mapping[cat]
        for evt in fp.get("events", []):
            if evt in mapping:
                return mapping[evt]
        return "Unknown"

    def _load_index(self) -> dict[str, Any]:
        if not self.index_path.exists():
            return {}
        try:
            return json.loads(self.index_path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return {}

    def _save_index(self, index: dict[str, Any]) -> None:
        try:
            self.index_path.parent.mkdir(parents=True, exist_ok=True)
            self.index_path.write_text(json.dumps(index, indent=2), encoding="utf-8")
        except OSError as exc:
            logger.warning("Similarity index save failed: %s", exc)
