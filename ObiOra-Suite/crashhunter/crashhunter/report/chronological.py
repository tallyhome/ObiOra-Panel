"""Chronological narrative report builder."""

from __future__ import annotations

from typing import Any


class ChronologicalReportBuilder:
    """
    Build a human-readable sequence-of-events narrative.
    The report explains WHAT happened and WHEN — not just raw metrics.
    """

    def build(
        self,
        timeline_events: list[dict[str, Any]],
        incident_summary: dict[str, Any] | None,
        diagnosis: dict[str, Any],
        similar_crashes: list[dict[str, Any]],
    ) -> dict[str, Any]:
        narrative_lines = self._build_narrative(timeline_events, incident_summary)
        phases = self._identify_phases(timeline_events)
        return {
            "narrative": narrative_lines,
            "phases": phases,
            "sequence_summary": self._sequence_summary(narrative_lines, diagnosis),
            "similar_crashes": similar_crashes,
            "probable_root_cause": self._root_cause(diagnosis, similar_crashes),
        }

    def _build_narrative(
        self,
        events: list[dict[str, Any]],
        incident: dict[str, Any] | None,
    ) -> list[str]:
        lines: list[str] = []
        if not events:
            lines.append("No timeline events recorded.")
            return lines

        lines.append(f"{events[0].get('timestamp', '?')}  System monitoring normal")
        for event in events:
            ts = event.get("timestamp", "?")
            detail = event.get("detail", event.get("event", ""))
            lines.append(f"{ts}  {detail}")

        if incident:
            lines.append(
                f"{incident.get('ended_at', '?')}  Incident mode collected "
                f"{incident.get('snapshot_count', 0)} emergency snapshots"
            )
        return lines

    def _identify_phases(self, events: list[dict[str, Any]]) -> list[dict[str, str]]:
        phases: list[dict[str, str]] = []
        if not events:
            return phases
        phases.append({"phase": "normal", "start": events[0].get("timestamp", ""), "end": ""})
        for event in events:
            if event.get("severity") in ("high", "critical"):
                phases.append({
                    "phase": "degradation",
                    "start": event.get("timestamp", ""),
                    "event": event.get("event", ""),
                })
            if event.get("event") == "incident_mode_started":
                phases.append({
                    "phase": "incident",
                    "start": event.get("timestamp", ""),
                    "detail": event.get("detail", ""),
                })
        return phases

    @staticmethod
    def _sequence_summary(narrative: list[str], diagnosis: dict[str, Any]) -> str:
        verdict = diagnosis.get("verdict", "UNKNOWN FREEZE")
        confidence = diagnosis.get("confidence", 0)
        event_count = len(narrative)
        return (
            f"Chronological analysis of {event_count} events. "
            f"Conclusion: {verdict} (confidence {confidence:.0%}). "
            f"{diagnosis.get('summary', '')}"
        )

    @staticmethod
    def _root_cause(diagnosis: dict[str, Any], similar: list[dict[str, Any]]) -> str:
        if diagnosis.get("findings"):
            return diagnosis["findings"][0].get("title", "Unknown")
        if similar:
            return similar[0].get("probable_root_cause", "Unknown")
        return "Unknown — silent freeze without kernel signature"
