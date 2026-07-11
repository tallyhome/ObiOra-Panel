"""Causal correlation engine — builds freeze story from events."""

from __future__ import annotations

from typing import Any

# Known causal chains for freeze diagnosis
CAUSAL_CHAINS: list[list[str]] = [
    ["iowait_increased", "qemu_storage_wait", "virsh_slow", "virtualizor_timeout", "ssh_timeout", "scheduler_stall", "incident_mode_started"],
    ["memory_pressure", "swap_storm", "oom", "hung_task"],
    ["smart_error", "disk_latency_spike", "d_state_explosion", "filesystem_freeze"],
    ["thermal_event", "cpu_saturation", "scheduler_blocked"],
    ["power_loss", "system_reboot_detected"],
    ["xfs_freeze", "d_state_explosion", "iowait_increased"],
]


class CorrelationEngine:
    """Correlate events into a causal narrative with arrows (↓)."""

    def correlate(self, events: list[dict[str, Any]]) -> dict[str, Any]:
        event_types = [e.get("event", "") for e in events]
        chain = self._find_best_chain(event_types)
        story_lines = self._build_story(events, chain)
        return {
            "causal_chain": chain,
            "story": story_lines,
            "story_text": "\n↓\n".join(story_lines),
            "event_count": len(events),
            "matched_chain_confidence": self._chain_confidence(event_types, chain),
        }

    def _find_best_chain(self, event_types: list[str]) -> list[str]:
        best: list[str] = []
        best_score = 0
        for chain in CAUSAL_CHAINS:
            score = sum(1 for e in chain if e in event_types)
            if score > best_score:
                best_score = score
                best = chain
        if best_score == 0:
            return event_types[:10]
        return [e for e in best if e in event_types]

    def _build_story(self, events: list[dict[str, Any]], chain: list[str]) -> list[str]:
        lines: list[str] = []
        event_map = {e.get("event"): e for e in events}
        for event_type in chain:
            entry = event_map.get(event_type)
            if entry:
                lines.append(f"{entry.get('timestamp', '?')}  {entry.get('detail', event_type)}")
            else:
                lines.append(f"?  {event_type.replace('_', ' ')}")
        if not lines and events:
            for e in events[-15:]:
                lines.append(f"{e.get('timestamp', '?')}  {e.get('detail', e.get('event', ''))}")
        return lines

    @staticmethod
    def _chain_confidence(event_types: list[str], chain: list[str]) -> float:
        if not chain:
            return 0.0
        matched = sum(1 for e in chain if e in event_types)
        return round(matched / len(chain), 2)
