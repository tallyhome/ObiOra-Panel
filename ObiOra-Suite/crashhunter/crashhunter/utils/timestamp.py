"""Microsecond-precision timestamps — UTC-normalized."""

from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any

logger = logging.getLogger("crashhunter.timestamp")

UTC = timezone.utc


def utc_now() -> datetime:
    return datetime.now(UTC)


def now_us() -> str:
    """Legacy local time-only (HH:MM:SS.ffffff) — prefer now_iso_us()."""
    return datetime.now().strftime("%H:%M:%S.%f")


def now_iso_us() -> str:
    """Return ISO-8601 UTC timestamp with microsecond precision."""
    return utc_now().strftime("%Y-%m-%dT%H:%M:%S.%fZ")


def format_us(dt: datetime) -> str:
    """Format a datetime with microsecond precision (local, legacy)."""
    return dt.strftime("%H:%M:%S.%f")


def make_timeline_entry(source: str = "crashhunter_daemon") -> dict[str, str]:
    """Build standard timeline timestamp fields."""
    original = now_us()
    utc = now_iso_us()
    return {
        "timestamp": utc,
        "timestamp_utc": utc,
        "timestamp_original": original,
        "timestamp_source": source,
    }


def parse_to_utc(value: str | None) -> datetime | None:
    """Parse ISO, epoch, or legacy time-only into UTC-aware datetime."""
    if not value:
        return None
    text = str(value).strip()
    if not text:
        return None

    if text.endswith("Z"):
        text = text[:-1] + "+00:00"

    try:
        if "T" in text or "+" in text or text.count("-") >= 2:
            dt = datetime.fromisoformat(text)
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=UTC)
            return dt.astimezone(UTC)
    except ValueError:
        pass

    if re_match_time_only(text):
        today = utc_now().date()
        try:
            parts = text.split(".")
            hms = parts[0]
            micro = parts[1] if len(parts) > 1 else "0"
            dt = datetime.strptime(f"{today} {hms}.{micro[:6]}", "%Y-%m-%d %H:%M:%S.%f")
            return dt.replace(tzinfo=UTC)
        except ValueError:
            return None

    return None


def re_match_time_only(text: str) -> bool:
    import re
    return bool(re.match(r"^\d{2}:\d{2}:\d{2}(\.\d+)?$", text))


def normalize_timeline_entry(entry: dict[str, Any]) -> dict[str, Any]:
    """Ensure timestamp_utc on a timeline entry (legacy compat)."""
    normalized = dict(entry)
    utc_val = entry.get("timestamp_utc") or entry.get("timestamp_us") or entry.get("timestamp")
    parsed = parse_to_utc(str(utc_val) if utc_val else None)
    if parsed is not None:
        iso = parsed.strftime("%Y-%m-%dT%H:%M:%S.%fZ")
        normalized["timestamp_utc"] = iso
        normalized["timestamp"] = iso
        if "timestamp_original" not in normalized:
            normalized["timestamp_original"] = str(entry.get("timestamp", ""))
        if "timestamp_source" not in normalized:
            normalized["timestamp_source"] = "legacy_migrated"
    return normalized


def normalize_timeline_entries(events: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return [normalize_timeline_entry(e) for e in events]


def sort_timeline_utc(events: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], dict[str, Any] | None]:
    """Sort by timestamp_utc; return integrity warning if order was inverted."""
    if len(events) < 2:
        return events, None

    parsed: list[tuple[datetime | None, dict[str, Any]]] = []
    for entry in events:
        ts = parse_to_utc(str(entry.get("timestamp_utc") or entry.get("timestamp") or ""))
        parsed.append((ts, entry))

    if any(p[0] is None for p in parsed):
        return events, {"warning": "timeline_integrity_warning", "reason": "unparseable_timestamps"}

    original_order = [p[0] for p in parsed if p[0] is not None]
    sorted_parsed = sorted(parsed, key=lambda x: x[0] or utc_now())
    sorted_order = [p[0] for p in sorted_parsed if p[0] is not None]

    integrity = None
    if original_order != sorted_order:
        logger.warning("Timeline temporal inversion corrected after UTC normalization")
        integrity = {
            "warning": "timeline_integrity_warning",
            "reason": "inversion_corrected",
            "event_count": len(events),
        }

    return [p[1] for p in sorted_parsed], integrity


def utc_iso_from_ns(wall_ns: int) -> str:
    return datetime.fromtimestamp(wall_ns / 1_000_000_000, tz=UTC).strftime("%Y-%m-%dT%H:%M:%S.%fZ")
