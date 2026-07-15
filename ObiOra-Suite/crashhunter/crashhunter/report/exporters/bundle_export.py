"""Diagnostic bundle export — tar.zst compact, sans dossier staging permanent."""

from __future__ import annotations

import json
import logging
import shutil
import subprocess
import tarfile
import tempfile
from datetime import datetime
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.bundle")


def export_bundle(
    report: dict[str, Any],
    report_dir: Path,
    base_dir: Path,
    include_ring: bool = False,
) -> Path:
    """
    Create crashhunter-YYYYMMDD_HHMMSS.tar.zst (ou .tar) containing:
    report, optional ring, incidents, logs, config, metadata.

    Ne laisse PAS de dossier staging dans bundles/ (evite les fuites 300 Mo x N
    quand le process est tue OOM avant rmtree).
    """
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    bundle_name = f"crashhunter-{ts}"
    bundles_root = base_dir / "bundles"
    bundles_root.mkdir(parents=True, exist_ok=True)

    # Purge d'anciens staging abandonnes (crash / OOM mid-export)
    _cleanup_orphan_staging(bundles_root)

    with tempfile.TemporaryDirectory(prefix="ch-bundle-") as tmp:
        staging = Path(tmp) / bundle_name
        staging.mkdir(parents=True, exist_ok=True)

        if report_dir.exists():
            # Copier seulement les petits fichiers texte du rapport (pas de perf/ftrace bruts massifs)
            _copy_lean_tree(report_dir, staging / "report", max_file_bytes=2_000_000)

        manifest = {
            "bundle_id": bundle_name,
            "created_at": datetime.utcnow().isoformat() + "Z",
            "report_id": report.get("report_id"),
            "hostname": report.get("hostname"),
            "version": report.get("crashhunter_version"),
            "reboot_type": report.get("reboot_classification", {}).get("reboot_type"),
            "verdict": report.get("diagnosis", {}).get("verdict"),
            "lean": True,
        }
        (staging / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")

        config_src = base_dir / "config.yaml"
        if config_src.exists():
            shutil.copy2(config_src, staging / "config.yaml")

        logs_dir = base_dir / "logs"
        if logs_dir.exists():
            dest = staging / "logs"
            dest.mkdir(exist_ok=True)
            for log_file in sorted(logs_dir.glob("*.log"))[-3:]:
                if log_file.stat().st_size <= 5_000_000:
                    shutil.copy2(log_file, dest / log_file.name)

        if include_ring:
            ring_dir = base_dir / "data" / "ring"
            if ring_dir.exists():
                _copy_lean_tree(ring_dir, staging / "ring", max_file_bytes=500_000, max_files=50)

        incidents_dir = base_dir / "data" / "incidents"
        if incidents_dir.exists():
            _copy_lean_tree(incidents_dir, staging / "incidents", max_file_bytes=1_000_000, max_files=20)

        timeline_file = base_dir / "data" / "state" / "event_timeline.jsonl"
        if timeline_file.exists() and timeline_file.stat().st_size <= 2_000_000:
            shutil.copy2(timeline_file, staging / "event_timeline.jsonl")

        tar_path = bundles_root / f"{bundle_name}.tar"
        with tarfile.open(tar_path, "w:gz") as tar:
            tar.add(staging, arcname=bundle_name)

    zst_path = bundles_root / f"{bundle_name}.tar.zst"
    if shutil.which("zstd") and tar_path.exists():
        subprocess.run(["zstd", "-f", "-19", str(tar_path), "-o", str(zst_path)], check=False)
        if zst_path.exists():
            tar_path.unlink(missing_ok=True)
            final = zst_path
        else:
            final = tar_path
    else:
        final = tar_path

    logger.info("Diagnostic bundle created: %s (%s bytes)", final, final.stat().st_size if final.exists() else 0)
    return final


def _cleanup_orphan_staging(bundles_root: Path) -> None:
    """Supprime les dossiers crashhunter-* laisses par un export interrompu."""
    if not bundles_root.exists():
        return
    for entry in bundles_root.iterdir():
        if entry.is_dir() and entry.name.startswith("crashhunter-"):
            shutil.rmtree(entry, ignore_errors=True)
            logger.info("Removed orphan bundle staging: %s", entry)


def _copy_lean_tree(
    src: Path,
    dest: Path,
    max_file_bytes: int = 2_000_000,
    max_files: int = 200,
) -> None:
    dest.mkdir(parents=True, exist_ok=True)
    copied = 0
    # Prefer recent files
    files = sorted(src.rglob("*"), key=lambda p: p.stat().st_mtime if p.is_file() else 0, reverse=True)
    for path in files:
        if not path.is_file():
            continue
        if path.stat().st_size > max_file_bytes:
            continue
        # Skip heavy binary traces
        name = path.name.lower()
        if name.endswith((".perf", ".data", ".raw", ".bin", ".pcap")):
            continue
        if "ftrace" in name or "perf-" in name:
            continue
        rel = path.relative_to(src)
        target = dest / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        try:
            shutil.copy2(path, target)
            copied += 1
        except OSError:
            continue
        if copied >= max_files:
            break
