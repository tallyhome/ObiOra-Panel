"""Circular ring buffer for 720 snapshots (60 minutes at 5s interval)."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.ring")


class RingBuffer:
    """In-memory circular buffer with disk persistence for crash survival."""

    def __init__(self, capacity: int, ring_dir: Path) -> None:
        self.capacity = capacity
        self.ring_dir = ring_dir
        self.ring_dir.mkdir(parents=True, exist_ok=True)
        self._buffer: list[dict[str, Any] | None] = [None] * capacity
        self._index = 0
        self._count = 0
        self._meta_file = ring_dir / "meta.json"
        self._load_meta()

    @property
    def count(self) -> int:
        return self._count

    def append(self, snapshot: dict[str, Any]) -> int:
        """Add snapshot to ring; returns slot index."""
        slot = self._index
        self._buffer[slot] = snapshot
        self._write_slot(slot, snapshot)
        self._index = (self._index + 1) % self.capacity
        self._count = min(self._count + 1, self.capacity)
        self._save_meta()
        return slot

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

    def _slot_path(self, slot: int) -> Path:
        return self.ring_dir / f"snap_{slot:04d}.json"

    def _write_slot(self, slot: int, snapshot: dict[str, Any]) -> None:
        path = self._slot_path(slot)
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
        if not self._meta_file.exists():
            return
        try:
            meta = json.loads(self._meta_file.read_text(encoding="utf-8"))
            self._index = int(meta.get("index", 0))
            self._count = int(meta.get("count", 0))
        except (OSError, json.JSONDecodeError, ValueError) as exc:
            logger.warning("Could not load ring meta: %s", exc)

    def _load_all_from_disk(self) -> list[dict[str, Any]]:
        snapshots: list[tuple[int, dict[str, Any]]] = []
        for path in self.ring_dir.glob("snap_*.json"):
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
        for path in self.ring_dir.glob("snap_*.json"):
            try:
                slot = int(path.stem.split("_")[1])
                if 0 <= slot < self.capacity:
                    self._buffer[slot] = json.loads(path.read_text(encoding="utf-8"))
            except (ValueError, json.JSONDecodeError, OSError):
                continue
