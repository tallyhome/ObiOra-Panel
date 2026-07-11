"""Circular ring buffer with optional tmpfs + periodic disk sync."""

from __future__ import annotations

import json
import logging
import shutil
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.ring")


class RingBuffer:
    """In-memory circular buffer with optional deferred disk persistence."""

    def __init__(
        self,
        capacity: int,
        ring_dir: Path,
        sync_dir: Path | None = None,
        defer_disk_writes: bool = False,
    ) -> None:
        self.capacity = capacity
        self.ring_dir = ring_dir
        self.sync_dir = sync_dir or ring_dir
        self.defer_disk_writes = defer_disk_writes
        self.ring_dir.mkdir(parents=True, exist_ok=True)
        self.sync_dir.mkdir(parents=True, exist_ok=True)
        self._buffer: list[dict[str, Any] | None] = [None] * capacity
        self._index = 0
        self._count = 0
        self._dirty_slots: set[int] = set()
        self._meta_file = self.ring_dir / "meta.json"
        self._sync_meta_file = self.sync_dir / "meta.json"
        self._load_meta()

    @property
    def count(self) -> int:
        return self._count

    def append(self, snapshot: dict[str, Any]) -> int:
        """Add snapshot to ring; returns slot index."""
        slot = self._index
        self._buffer[slot] = snapshot
        if self.defer_disk_writes:
            self._dirty_slots.add(slot)
        else:
            self._write_slot(slot, snapshot, self.ring_dir)
            self._write_slot(slot, snapshot, self.sync_dir)
        self._index = (self._index + 1) % self.capacity
        self._count = min(self._count + 1, self.capacity)
        self._save_meta()
        return slot

    def sync_to_disk(self) -> int:
        """Flush dirty in-memory slots to persistent sync_dir."""
        synced = 0
        for slot in list(self._dirty_slots):
            snap = self._buffer[slot]
            if snap is not None:
                self._write_slot(slot, snap, self.sync_dir)
                synced += 1
        self._dirty_slots.clear()
        if synced:
            meta = {"index": self._index, "count": self._count, "capacity": self.capacity}
            try:
                self._sync_meta_file.write_text(json.dumps(meta), encoding="utf-8")
            except OSError as exc:
                logger.error("Failed to save sync meta: %s", exc)
        return synced

    def get_all_ordered(self) -> list[dict[str, Any]]:
        """Return snapshots in chronological order."""
        if self._count == 0:
            return self._load_all_from_disk()
        if self._count < self.capacity:
            return [s for s in self._buffer[: self._count] if s is not None]
        ordered: list[dict[str, Any]] = []
        for i in range(self.capacity):
            idx = (self._index + i) % self.capacity
            snap = self._buffer[idx]
            if snap is not None:
                ordered.append(snap)
        return ordered

    def _slot_path(self, slot: int, base: Path) -> Path:
        return base / f"snap_{slot:04d}.json"

    def _write_slot(self, slot: int, snapshot: dict[str, Any], base: Path) -> None:
        path = self._slot_path(slot, base)
        try:
            path.write_text(json.dumps(snapshot, ensure_ascii=False), encoding="utf-8")
        except OSError as exc:
            logger.error("Failed to write ring slot %s: %s", slot, exc)

    def _save_meta(self) -> None:
        meta = {"index": self._index, "count": self._count, "capacity": self.capacity}
        try:
            self._meta_file.write_text(json.dumps(meta), encoding="utf-8")
        except OSError as exc:
            logger.error("Failed to save ring meta: %s", exc)

    def _load_meta(self) -> None:
        for meta_path in (self._meta_file, self._sync_meta_file):
            if not meta_path.exists():
                continue
            try:
                meta = json.loads(meta_path.read_text(encoding="utf-8"))
                self._index = int(meta.get("index", 0))
                self._count = int(meta.get("count", 0))
                break
            except (OSError, json.JSONDecodeError, ValueError) as exc:
                logger.warning("Could not load ring meta: %s", exc)

    def _load_all_from_disk(self) -> list[dict[str, Any]]:
        base = self.sync_dir if self.sync_dir.exists() else self.ring_dir
        snapshots: list[tuple[int, dict[str, Any]]] = []
        for path in base.glob("snap_*.json"):
            try:
                slot = int(path.stem.split("_")[1])
                data = json.loads(path.read_text(encoding="utf-8"))
                snapshots.append((slot, data))
            except (ValueError, json.JSONDecodeError, OSError):
                continue
        if not snapshots:
            return []
        if self._count >= self.capacity:
            start = self._index
            ordered_slots = [(start + i) % self.capacity for i in range(self.capacity)]
            slot_map = {s: d for s, d in snapshots}
            return [slot_map[s] for s in ordered_slots if s in slot_map]
        snapshots.sort(key=lambda x: x[0])
        return [data for _, data in snapshots]

    def load_from_disk(self) -> None:
        """Hydrate in-memory buffer from persisted ring files."""
        base = self.sync_dir if list(self.sync_dir.glob("snap_*.json")) else self.ring_dir
        for path in base.glob("snap_*.json"):
            try:
                slot = int(path.stem.split("_")[1])
                if 0 <= slot < self.capacity:
                    self._buffer[slot] = json.loads(path.read_text(encoding="utf-8"))
            except (ValueError, json.JSONDecodeError, OSError):
                continue

    @staticmethod
    def ensure_tmpfs(tmpfs_path: Path, size_mb: int = 128) -> bool:
        """Mount tmpfs at path if not already mounted (Linux only)."""
        if tmpfs_path.exists() and any(tmpfs_path.iterdir()):
            return True
        tmpfs_path.mkdir(parents=True, exist_ok=True)
        import shutil
        if not shutil.which("mount"):
            return False
        from crashhunter.utils.subprocess_runner import SubprocessRunner
        runner = SubprocessRunner(default_timeout=5.0)
        result = runner.run(
            ["mount", "-t", "tmpfs", "-o", f"size={size_mb}M", "crashhunter-ring", str(tmpfs_path)],
            timeout=5.0,
        )
        return result.returncode == 0
