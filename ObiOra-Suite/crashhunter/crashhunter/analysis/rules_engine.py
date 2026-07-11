"""Rules engine — add detections via YAML without modifying core."""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Any

import yaml

from crashhunter.report.event_timeline import EventTimeline

logger = logging.getLogger("crashhunter.rules")


class RulesEngine:
    """Evaluate YAML-defined rules against snapshots and emit timeline events."""

    def __init__(self, rules_path: Path | None = None, timeline: EventTimeline | None = None) -> None:
        self.timeline = timeline
        pkg_rules = Path(__file__).parent / "rules" / "default.yaml"
        self.rules_path = rules_path or pkg_rules
        self.rules: list[dict[str, Any]] = self._load_rules()

    def _load_rules(self) -> list[dict[str, Any]]:
        if not self.rules_path.exists():
            return []
        try:
            data = yaml.safe_load(self.rules_path.read_text(encoding="utf-8")) or {}
            return list(data.get("rules", []))
        except (OSError, yaml.YAMLError) as exc:
            logger.warning("Failed to load rules: %s", exc)
            return []

    def evaluate(self, snapshot: dict[str, Any]) -> list[dict[str, Any]]:
        """Return list of triggered rule matches."""
        triggered: list[dict[str, Any]] = []
        for rule in self.rules:
            match = self._evaluate_rule(rule, snapshot)
            if match:
                triggered.append(match)
                if self.timeline:
                    self.timeline.record(
                        match["event"],
                        match["message"],
                        severity=match["severity"],
                        extra={"rule_id": match["rule_id"]},
                    )
        return triggered

    def _evaluate_rule(self, rule: dict[str, Any], snapshot: dict[str, Any]) -> dict[str, Any] | None:
        field = rule.get("match_field", "")
        value = self._get_nested(snapshot, field)
        op = rule.get("operator", "gt")
        threshold = rule.get("threshold")

        fired = False
        if op == "gt" and isinstance(value, (int, float)) and value > threshold:
            fired = True
        elif op == "gte" and isinstance(value, (int, float)) and value >= threshold:
            fired = True
        elif op == "eq" and value == threshold:
            fired = True
        elif op == "contains" and isinstance(value, str) and str(threshold).lower() in value.lower():
            fired = True
        elif op == "ratio_lt":
            denom = self._get_nested(snapshot, rule.get("threshold_field", ""))
            if isinstance(value, (int, float)) and isinstance(denom, (int, float)) and denom > 0:
                if value / denom < rule.get("ratio", 0.05):
                    fired = True

        if not fired:
            return None

        message = str(rule.get("message", rule.get("id", ""))).replace("{value}", str(value))
        return {
            "rule_id": rule.get("id"),
            "event": rule.get("event"),
            "severity": rule.get("severity", "medium"),
            "message": message,
            "field": field,
            "value": value,
        }

    @staticmethod
    def _get_nested(data: dict[str, Any], path: str) -> Any:
        parts = path.split(".")
        current: Any = data
        for part in parts:
            if not isinstance(current, dict):
                return None
            current = current.get(part)
        return current
