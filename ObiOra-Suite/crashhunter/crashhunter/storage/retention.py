"""Report / bundle retention and compression."""

from __future__ import annotations

import logging
import shutil
import subprocess
import tarfile
import time
from pathlib import Path

logger = logging.getLogger("crashhunter.retention")


class RetentionManager:
    """Compress and purge old reports + bundles per retention policy."""

    def __init__(
        self,
        reports_dir: Path,
        retention_days: int,
        compress_after_days: int,
        bundles_dir: Path | None = None,
        max_bundles: int = 5,
    ) -> None:
        self.reports_dir = reports_dir
        self.bundles_dir = bundles_dir
        self.retention_days = retention_days
        self.compress_after_days = compress_after_days
        self.max_bundles = max(0, max_bundles)

    def run(self) -> dict[str, int]:
        now = time.time()
        compressed = 0
        deleted = 0
        bundles_deleted = 0

        if self.reports_dir.exists():
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

        if self.bundles_dir is not None and self.bundles_dir.exists():
            bundles_deleted += self._purge_old_dirs(self.bundles_dir, now)
            bundles_deleted += self._cap_dirs(self.bundles_dir, self.max_bundles)

        return {
            "compressed": compressed,
            "deleted": deleted,
            "bundles_deleted": bundles_deleted,
        }

    def _purge_old_dirs(self, directory: Path, now: float) -> int:
        deleted = 0
        for entry in directory.iterdir():
            if not entry.is_dir():
                continue
            age_days = (now - entry.stat().st_mtime) / 86400
            if age_days > self.retention_days:
                shutil.rmtree(entry, ignore_errors=True)
                deleted += 1
        return deleted

    def _cap_dirs(self, directory: Path, keep: int) -> int:
        if keep <= 0:
            return 0
        # Dossiers staging orphelins (exports OOM) — toujours a supprimer
        deleted = 0
        for entry in list(directory.iterdir()):
            if entry.is_dir() and entry.name.startswith("crashhunter-"):
                shutil.rmtree(entry, ignore_errors=True)
                deleted += 1

        archives = sorted(
            [p for p in directory.iterdir() if p.is_file() and p.name.startswith("crashhunter-")],
            key=lambda p: p.stat().st_mtime,
            reverse=True,
        )
        for entry in archives[keep:]:
            entry.unlink(missing_ok=True)
            deleted += 1
        return deleted

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
