"""Diagnostic bundle export — tar.zst ready for OVH support."""

from __future__ import annotations

import json
import logging
import shutil
import subprocess
import tarfile
from datetime import datetime
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.bundle")


def export_bundle(
    report: dict[str, Any],
    report_dir: Path,
    base_dir: Path,
    include_ring: bool = True,
) -> Path:
    """
    Create crashhunter-YYYYMMDD-HHMMSS.tar.zst containing:
    report, snapshots, incidents, logs, config, metadata.
    """
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    bundle_name = f"crashhunter-{ts}"
    staging = base_dir / "bundles" / bundle_name
    staging.mkdir(parents=True, exist_ok=True)

    # Report files
    if report_dir.exists():
        shutil.copytree(report_dir, staging / "report", dirs_exist_ok=True)

    # Metadata manifest
    manifest = {
        "bundle_id": bundle_name,
        "created_at": datetime.utcnow().isoformat() + "Z",
        "report_id": report.get("report_id"),
        "hostname": report.get("hostname"),
        "version": report.get("crashhunter_version"),
        "reboot_type": report.get("reboot_classification", {}).get("reboot_type"),
        "verdict": report.get("diagnosis", {}).get("verdict"),
    }
    (staging / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")

    # Config
    config_src = base_dir / "config.yaml"
    if config_src.exists():
        shutil.copy2(config_src, staging / "config.yaml")

    # Logs
    logs_dir = base_dir / "logs"
    if logs_dir.exists():
        shutil.copytree(logs_dir, staging / "logs", dirs_exist_ok=True)

    # Ring buffer (last snapshots)
    if include_ring:
        ring_dir = base_dir / "data" / "ring"
        if ring_dir.exists():
            shutil.copytree(ring_dir, staging / "ring", dirs_exist_ok=True)

    # Incidents
    incidents_dir = base_dir / "data" / "incidents"
    if incidents_dir.exists():
        shutil.copytree(incidents_dir, staging / "incidents", dirs_exist_ok=True)

    # Timeline
    timeline_file = base_dir / "data" / "state" / "event_timeline.jsonl"
    if timeline_file.exists():
        shutil.copy2(timeline_file, staging / "event_timeline.jsonl")

    archive_base = base_dir / "bundles" / bundle_name
    tar_path = archive_base.with_suffix(".tar")

    with tarfile.open(tar_path, "w") as tar:
        tar.add(staging, arcname=bundle_name)

    zst_path = tar_path.with_suffix(".tar.zst")
    if shutil.which("zstd"):
        subprocess.run(["zstd", "-f", "-19", str(tar_path), "-o", str(zst_path)], check=False)
        tar_path.unlink(missing_ok=True)
        final = zst_path
    else:
        final = tar_path

    shutil.rmtree(staging, ignore_errors=True)
    logger.info("Diagnostic bundle created: %s", final)
    return final
