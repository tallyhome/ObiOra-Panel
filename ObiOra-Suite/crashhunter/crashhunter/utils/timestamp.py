"""Microsecond-precision timestamps."""

from __future__ import annotations

from datetime import datetime, timezone


def now_us() -> str:
    """Return local-time timestamp with microsecond precision: HH:MM:SS.ffffff."""
    return datetime.now().strftime("%H:%M:%S.%f")


def now_iso_us() -> str:
    """Return ISO-8601 UTC timestamp with microsecond precision."""
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.%fZ")


def format_us(dt: datetime) -> str:
    """Format a datetime with microsecond precision."""
    return dt.strftime("%H:%M:%S.%f")
