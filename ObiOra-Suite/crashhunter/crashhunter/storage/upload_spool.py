"""Persistent upload spool — local-first delivery to ObiOra Panel."""

from __future__ import annotations

import json
import logging
import os
import tempfile
import time
import uuid
from pathlib import Path
from typing import Any

logger = logging.getLogger("crashhunter.upload_spool")


class UploadSpool:
    """Queue failed panel uploads for retry after reboot."""

    def __init__(self, spool_dir: Path) -> None:
        self.spool_dir = spool_dir
        self.spool_dir.mkdir(parents=True, exist_ok=True)

    def enqueue(self, kind: str, payload: dict[str, Any], idempotency_key: str) -> Path:
        safe_key = idempotency_key.replace("/", "_").replace("\\", "_")[:120]
        path = self.spool_dir / f"{kind}_{safe_key}_{uuid.uuid4().hex[:8]}.json"
        record = {
            "kind": kind,
            "idempotency_key": idempotency_key,
            "enqueued_at": time.time(),
            "attempts": 0,
            "payload": payload,
        }
        self._atomic_write(path, record)
        logger.info("Spooled %s upload: %s", kind, path.name)
        return path

    def pending(self) -> list[Path]:
        if not self.spool_dir.exists():
            return []
        return sorted(self.spool_dir.glob("*.json"))

    def load(self, path: Path) -> dict[str, Any] | None:
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
            return data if isinstance(data, dict) else None
        except (OSError, json.JSONDecodeError):
            return None

    def mark_attempt(self, path: Path) -> None:
        data = self.load(path)
        if data is None:
            return
        data["attempts"] = int(data.get("attempts", 0)) + 1
        data["last_attempt_at"] = time.time()
        self._atomic_write(path, data)

    def ack(self, path: Path) -> None:
        try:
            path.unlink(missing_ok=True)
        except OSError as exc:
            logger.warning("Spool ack failed: %s", exc)

    def pending_count(self) -> int:
        return len(self.pending())

    @staticmethod
    def _atomic_write(path: Path, payload: dict[str, Any]) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        text = json.dumps(payload, ensure_ascii=False)
        fd, tmp = tempfile.mkstemp(prefix=f".{path.name}.", dir=str(path.parent))
        tmp_path = Path(tmp)
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as fh:
                fh.write(text)
                fh.flush()
                os.fsync(fh.fileno())
            os.replace(tmp_path, path)
        finally:
            if tmp_path.exists():
                tmp_path.unlink(missing_ok=True)
