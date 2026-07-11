"""Report retention and compression."""

from __future__ import annotations

import logging
import shutil
import subprocess
import tarfile
import time
from pathlib import Path

logger = logging.getLogger("crashhunter.retention")


class RetentionManager:
    """Compress and purge old reports per retention policy."""

    def __init__(self, reports_dir: Path, retention_days: int, compress_after_days: int) -> None:
        self.reports_dir = reports_dir
        self.retention_days = retention_days
        self.compress_after_days = compress_after_days

    def run(self) -> dict[str, int]:
        now = time.time()
        compressed = 0
        deleted = 0
        if not self.reports_dir.exists():
            return {"compressed": 0, "deleted": 0}

        for entry in self.reports_dir.iterdir():
            if not entry.is_dir() or not entry.name.startswith("CrashReport_"):
                continue
            age_days = (now - entry.stat().st_mtime) / 86400
            archive = entry.with_suffix(".tar.zst")

            if age_days > self.retention_days:
                shutil.rmtree(entry, ignore_errors=True)
                archive.unlink(missing_ok=True)
                deleted += 1
            elif age_days > self.compress_after_days and not archive.exists():
                if self._compress_report(entry):
                    compressed += 1

        return {"compressed": compressed, "deleted": deleted}

    def _compress_report(self, report_dir: Path) -> bool:
        tar_path = report_dir.with_suffix(".tar")
        try:
            with tarfile.open(tar_path, "w:gz") as tar:
                tar.add(report_dir, arcname=report_dir.name)
            if shutil.which("zstd"):
                zst = tar_path.with_suffix(".tar.zst")
                subprocess.run(["zstd", "-f", "-15", str(tar_path), "-o", str(zst)], check=False)
                tar_path.unlink(missing_ok=True)
            shutil.rmtree(report_dir, ignore_errors=True)
            return True
        except OSError as exc:
            logger.warning("Compression failed for %s: %s", report_dir, exc)
            return False
