"""Stockage circulaire SQLite / PostgreSQL pour métriques et événements."""

from __future__ import annotations

import json
import sqlite3
import time
from abc import ABC, abstractmethod
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Generator, Iterator


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


class MetricsStorage(ABC):
    """Interface de stockage des métriques et événements."""

    @abstractmethod
    def initialize(self) -> None: ...

    @abstractmethod
    def insert_metric(self, collector: str, payload: dict[str, Any], sampled_at: float | None = None) -> None: ...

    @abstractmethod
    def insert_event(self, event_type: str, severity: str, title: str, details: str, payload: dict[str, Any]) -> None: ...

    @abstractmethod
    def prune_old(self, retention_seconds: int) -> int: ...

    @abstractmethod
    def metrics_since(self, since_ts: float) -> list[dict[str, Any]]: ...

    @abstractmethod
    def events_since(self, since_ts: float) -> list[dict[str, Any]]: ...

    @abstractmethod
    def recent_events(self, limit: int = 100) -> list[dict[str, Any]]: ...


class SqliteMetricsStorage(MetricsStorage):
    """Implémentation SQLite avec historique circulaire."""

    def __init__(self, db_path: str) -> None:
        self.db_path = db_path
        Path(db_path).parent.mkdir(parents=True, exist_ok=True)

    @contextmanager
    def _conn(self) -> Generator[sqlite3.Connection, None, None]:
        conn = sqlite3.connect(self.db_path, timeout=10)
        conn.row_factory = sqlite3.Row
        try:
            yield conn
            conn.commit()
        finally:
            conn.close()

    def initialize(self) -> None:
        with self._conn() as conn:
            conn.executescript(
                """
                CREATE TABLE IF NOT EXISTS metrics (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    collector TEXT NOT NULL,
                    sampled_at REAL NOT NULL,
                    payload TEXT NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_metrics_sampled ON metrics(sampled_at);
                CREATE INDEX IF NOT EXISTS idx_metrics_collector ON metrics(collector, sampled_at);

                CREATE TABLE IF NOT EXISTS events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_type TEXT NOT NULL,
                    severity TEXT NOT NULL,
                    title TEXT NOT NULL,
                    details TEXT,
                    payload TEXT,
                    detected_at REAL NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_events_detected ON events(detected_at);
                CREATE INDEX IF NOT EXISTS idx_events_type ON events(event_type, detected_at);
                """
            )

    def insert_metric(self, collector: str, payload: dict[str, Any], sampled_at: float | None = None) -> None:
        ts = sampled_at or time.time()
        with self._conn() as conn:
            conn.execute(
                "INSERT INTO metrics (collector, sampled_at, payload) VALUES (?, ?, ?)",
                (collector, ts, json.dumps(payload, separators=(",", ":"))),
            )

    def insert_event(
        self,
        event_type: str,
        severity: str,
        title: str,
        details: str,
        payload: dict[str, Any],
    ) -> None:
        with self._conn() as conn:
            conn.execute(
                "INSERT INTO events (event_type, severity, title, details, payload, detected_at) VALUES (?, ?, ?, ?, ?, ?)",
                (event_type, severity, title, details, json.dumps(payload), time.time()),
            )

    def prune_old(self, retention_seconds: int) -> int:
        cutoff = time.time() - retention_seconds
        with self._conn() as conn:
            cur_m = conn.execute("DELETE FROM metrics WHERE sampled_at < ?", (cutoff,))
            cur_e = conn.execute("DELETE FROM events WHERE detected_at < ?", (cutoff,))
            return (cur_m.rowcount or 0) + (cur_e.rowcount or 0)

    def metrics_since(self, since_ts: float) -> list[dict[str, Any]]:
        with self._conn() as conn:
            rows = conn.execute(
                "SELECT collector, sampled_at, payload FROM metrics WHERE sampled_at >= ? ORDER BY sampled_at",
                (since_ts,),
            ).fetchall()
        return [
            {
                "collector": row["collector"],
                "sampled_at": row["sampled_at"],
                "payload": json.loads(row["payload"]),
            }
            for row in rows
        ]

    def events_since(self, since_ts: float) -> list[dict[str, Any]]:
        with self._conn() as conn:
            rows = conn.execute(
                "SELECT * FROM events WHERE detected_at >= ? ORDER BY detected_at",
                (since_ts,),
            ).fetchall()
        return [self._row_to_event(row) for row in rows]

    def recent_events(self, limit: int = 100) -> list[dict[str, Any]]:
        with self._conn() as conn:
            rows = conn.execute(
                "SELECT * FROM events ORDER BY detected_at DESC LIMIT ?",
                (limit,),
            ).fetchall()
        return [self._row_to_event(row) for row in rows]

    @staticmethod
    def _row_to_event(row: sqlite3.Row) -> dict[str, Any]:
        return {
            "event_type": row["event_type"],
            "severity": row["severity"],
            "title": row["title"],
            "details": row["details"],
            "payload": json.loads(row["payload"] or "{}"),
            "detected_at": row["detected_at"],
        }


class PostgresMetricsStorage(MetricsStorage):
    """Implémentation PostgreSQL (psycopg2 optionnel)."""

    def __init__(self, dsn: str) -> None:
        self.dsn = dsn
        try:
            import psycopg2  # type: ignore
            import psycopg2.extras  # type: ignore

            self._psycopg2 = psycopg2
            self._extras = psycopg2.extras
        except ImportError as exc:
            raise RuntimeError("psycopg2 requis pour le backend PostgreSQL") from exc

    @contextmanager
    def _conn(self) -> Iterator[Any]:
        conn = self._psycopg2.connect(self.dsn)
        try:
            yield conn
            conn.commit()
        finally:
            conn.close()

    def initialize(self) -> None:
        with self._conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    CREATE TABLE IF NOT EXISTS metrics (
                        id SERIAL PRIMARY KEY,
                        collector TEXT NOT NULL,
                        sampled_at DOUBLE PRECISION NOT NULL,
                        payload JSONB NOT NULL
                    );
                    CREATE INDEX IF NOT EXISTS idx_metrics_sampled ON metrics(sampled_at);
                    CREATE TABLE IF NOT EXISTS events (
                        id SERIAL PRIMARY KEY,
                        event_type TEXT NOT NULL,
                        severity TEXT NOT NULL,
                        title TEXT NOT NULL,
                        details TEXT,
                        payload JSONB,
                        detected_at DOUBLE PRECISION NOT NULL
                    );
                    CREATE INDEX IF NOT EXISTS idx_events_detected ON events(detected_at);
                    """
                )

    def insert_metric(self, collector: str, payload: dict[str, Any], sampled_at: float | None = None) -> None:
        ts = sampled_at or time.time()
        with self._conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO metrics (collector, sampled_at, payload) VALUES (%s, %s, %s)",
                    (collector, ts, json.dumps(payload)),
                )

    def insert_event(
        self,
        event_type: str,
        severity: str,
        title: str,
        details: str,
        payload: dict[str, Any],
    ) -> None:
        with self._conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO events (event_type, severity, title, details, payload, detected_at) VALUES (%s,%s,%s,%s,%s,%s)",
                    (event_type, severity, title, details, json.dumps(payload), time.time()),
                )

    def prune_old(self, retention_seconds: int) -> int:
        cutoff = time.time() - retention_seconds
        deleted = 0
        with self._conn() as conn:
            with conn.cursor() as cur:
                cur.execute("DELETE FROM metrics WHERE sampled_at < %s", (cutoff,))
                deleted += cur.rowcount or 0
                cur.execute("DELETE FROM events WHERE detected_at < %s", (cutoff,))
                deleted += cur.rowcount or 0
        return deleted

    def metrics_since(self, since_ts: float) -> list[dict[str, Any]]:
        with self._conn() as conn:
            with conn.cursor(cursor_factory=self._extras.RealDictCursor) as cur:
                cur.execute(
                    "SELECT collector, sampled_at, payload FROM metrics WHERE sampled_at >= %s ORDER BY sampled_at",
                    (since_ts,),
                )
                rows = cur.fetchall()
        return [
            {
                "collector": row["collector"],
                "sampled_at": float(row["sampled_at"]),
                "payload": row["payload"] if isinstance(row["payload"], dict) else json.loads(row["payload"]),
            }
            for row in rows
        ]

    def events_since(self, since_ts: float) -> list[dict[str, Any]]:
        with self._conn() as conn:
            with conn.cursor(cursor_factory=self._extras.RealDictCursor) as cur:
                cur.execute(
                    "SELECT * FROM events WHERE detected_at >= %s ORDER BY detected_at",
                    (since_ts,),
                )
                rows = cur.fetchall()
        return [self._row_to_event(row) for row in rows]

    def recent_events(self, limit: int = 100) -> list[dict[str, Any]]:
        with self._conn() as conn:
            with conn.cursor(cursor_factory=self._extras.RealDictCursor) as cur:
                cur.execute("SELECT * FROM events ORDER BY detected_at DESC LIMIT %s", (limit,))
                rows = cur.fetchall()
        return [self._row_to_event(row) for row in rows]

    @staticmethod
    def _row_to_event(row: dict[str, Any]) -> dict[str, Any]:
        payload = row.get("payload") or {}
        if isinstance(payload, str):
            payload = json.loads(payload)
        return {
            "event_type": row["event_type"],
            "severity": row["severity"],
            "title": row["title"],
            "details": row.get("details") or "",
            "payload": payload,
            "detected_at": float(row["detected_at"]),
        }


def create_storage(backend: str, sqlite_path: str, postgresql_dsn: str) -> MetricsStorage:
    """Fabrique le backend de stockage configuré."""
    if backend == "postgresql":
        if not postgresql_dsn:
            raise ValueError("postgresql_dsn requis pour le backend PostgreSQL")
        return PostgresMetricsStorage(postgresql_dsn)
    return SqliteMetricsStorage(sqlite_path)
