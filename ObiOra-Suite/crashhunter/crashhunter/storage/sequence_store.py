"""Monotone sequence_id for critical events — compare local vs witness."""

from __future__ import annotations

import json
import logging
import os
import tempfile
import threading
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.storage.sequence")


class SequenceStore:
    """Thread-safe monotone sequence counter with atomic JSON persistence."""

    def __init__(self, path: Path, tmpfs_path: Path | None = None) -> None:
        self.path = path
        self.tmpfs_path = tmpfs_path or path.parent / "sequence.tmpfs.json"
        self._lock = threading.Lock()
        self._seq = self._load()

    def next_id(self, event_type: str, payload: dict[str, Any] | None = None) -> int:
        with self._lock:
            self._seq += 1
            record = {
                "sequence_id": self._seq,
                "event_type": event_type,
                "payload": payload or {},
            }
            self._persist(record)
            return self._seq

    @property
    def current(self) -> int:
        with self._lock:
            return self._seq

    def last_record(self) -> dict[str, Any] | None:
        with self._lock:
            return self._read_file(self.path) or self._read_file(self.tmpfs_path)

    def compare_with_witness(self, witness_seq: int | None) -> dict[str, Any]:
        local = self.current
        if witness_seq is None:
            return {"local_seq": local, "witness_seq": None, "gap": None, "local_write_likely_dead": False}
        gap = witness_seq - local
        return {
            "local_seq": local,
            "witness_seq": witness_seq,
            "gap": gap,
            "local_write_likely_dead": gap > 0,
            "interpretation": (
                "Witness ahead of local — filesystem or disk writes likely blocked before crash"
                if gap > 0
                else "Local and witness sequences aligned"
            ),
        }

    def _load(self) -> int:
        for p in (self.tmpfs_path, self.path):
            data = self._read_file(p)
            if data and isinstance(data.get("sequence_id"), int):
                return int(data["sequence_id"])
        return 0

    def _persist(self, record: dict[str, Any]) -> None:
        for target in (self.tmpfs_path, self.path):
            try:
                target.parent.mkdir(parents=True, exist_ok=True)
                self._atomic_write(target, record)
            except OSError as exc:
                logger.debug("Sequence persist to %s failed: %s", target, exc)

    @staticmethod
    def _read_file(path: Path) -> dict[str, Any] | None:
        try:
            if path.is_file():
                return json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return None
        return None

    @staticmethod
    def _atomic_write(path: Path, record: dict[str, Any]) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        fd, tmp = tempfile.mkstemp(dir=str(path.parent), prefix=".seq-", suffix=".tmp")
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as fh:
                json.dump(record, fh)
            os.replace(tmp, path)
        except OSError:
            try:
                os.unlink(tmp)
            except OSError:
                pass
            raise
