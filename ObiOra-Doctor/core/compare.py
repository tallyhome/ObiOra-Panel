"""Report comparison utilities."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any


def load_report(path: Path) -> dict[str, Any]:
    """Load a report JSON file or directory containing report.json."""

    if path.is_dir():
        path = path / "report.json"
    with path.open(encoding="utf-8") as handle:
        return json.load(handle)


def _diff_metrics(left: dict[str, Any] | None, right: dict[str, Any] | None) -> list[dict[str, Any]]:
    """Compare metric dictionaries between two module results."""

    left = left or {}
    right = right or {}
    keys = sorted(set(left) | set(right))
    diffs: list[dict[str, Any]] = []
    for key in keys:
        left_value = left.get(key)
        right_value = right.get(key)
        if left_value != right_value:
            diffs.append(
                {
                    "metric": key,
                    "left": left_value,
                    "right": right_value,
                }
            )
    return diffs


def compare_reports(left: dict[str, Any], right: dict[str, Any]) -> dict[str, Any]:
    """Compare two report dictionaries and return a diff summary."""

    left_modules = {item["module"]: item for item in left.get("results", [])}
    right_modules = {item["module"]: item for item in right.get("results", [])}
    all_modules = sorted(set(left_modules) | set(right_modules))

    module_diffs: list[dict[str, Any]] = []
    metric_diffs: list[dict[str, Any]] = []

    for name in all_modules:
        left_item = left_modules.get(name)
        right_item = right_modules.get(name)
        if not left_item or not right_item:
            module_diffs.append(
                {
                    "module": name,
                    "change": "added" if right_item and not left_item else "removed",
                    "left_score": left_item["score"] if left_item else None,
                    "right_score": right_item["score"] if right_item else None,
                }
            )
            continue

        score_delta = right_item["score"] - left_item["score"]
        metrics_changed = _diff_metrics(left_item.get("metrics"), right_item.get("metrics"))
        if score_delta != 0 or left_item["status"] != right_item["status"] or metrics_changed:
            entry: dict[str, Any] = {
                "module": name,
                "change": "modified",
                "left_score": left_item["score"],
                "right_score": right_item["score"],
                "score_delta": score_delta,
                "left_status": left_item["status"],
                "right_status": right_item["status"],
            }
            if metrics_changed:
                entry["metrics"] = metrics_changed
                for item in metrics_changed:
                    metric_diffs.append({"module": name, **item})
            module_diffs.append(entry)

    return {
        "left_date": left.get("generated_at"),
        "right_date": right.get("generated_at"),
        "left_score": left.get("score"),
        "right_score": right.get("score"),
        "score_delta": right.get("score", 0) - left.get("score", 0),
        "modules": module_diffs,
        "metrics": metric_diffs,
        "critical_changes": [
            item for item in module_diffs if item.get("score_delta", 0) < -10
        ],
    }


def render_compare_text(diff: dict[str, Any]) -> str:
    """Render a comparison diff as plain text."""

    lines = [
        "OBIORA DOCTOR - COMPARAISON",
        f"Score: {diff['left_score']}% -> {diff['right_score']}% "
        f"({diff['score_delta']:+d})",
        "",
    ]
    for item in diff["modules"]:
        if item["change"] == "modified":
            lines.append(
                f"- {item['module']}: {item['left_score']}% -> "
                f"{item['right_score']}% ({item['score_delta']:+d})"
            )
            for metric in item.get("metrics", []):
                lines.append(
                    f"    * {metric['metric']}: {metric['left']!r} -> {metric['right']!r}"
                )
        else:
            lines.append(f"- {item['module']}: {item['change']}")
    return "\n".join(lines) + "\n"
