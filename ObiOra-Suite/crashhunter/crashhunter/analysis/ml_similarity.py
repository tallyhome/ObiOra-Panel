"""Crash learning engine — probabilistic root cause after N crashes."""

from __future__ import annotations

import logging
from collections import Counter
from typing import Any

from crashhunter.report.similarity import SimilarityEngine

logger = logging.getLogger("crashhunter.ml_similarity")


class CrashLearningEngine:
    """
    After min_crashes indexed incidents, predict probable root cause
    with confidence percentage based on historical patterns.
    """

    def __init__(self, similarity: SimilarityEngine, min_crashes: int = 20) -> None:
        self.similarity = similarity
        self.min_crashes = min_crashes

    def predict(self, report: dict[str, Any]) -> dict[str, Any]:
        index = self.similarity._load_index()
        crash_count = len(index)

        if crash_count < self.min_crashes:
            return {
                "ready": False,
                "crash_count": crash_count,
                "min_crashes": self.min_crashes,
                "message": f"Learning requires {self.min_crashes} crashes, currently {crash_count}",
            }

        similar = self.similarity.find_similar(report)
        root_causes = Counter()
        for fp in index.values():
            cause = self.similarity._infer_root_cause(fp)
            root_causes[cause] += 1

        current_fp = self.similarity.fingerprint(report)
        current_cause = self.similarity._infer_root_cause(current_fp)
        total = sum(root_causes.values())
        base_prob = root_causes.get(current_cause, 0) / max(total, 1)

        best_match = similar[0] if similar else None
        match_confidence = best_match.get("confidence", 0) if best_match else 0

        if best_match and match_confidence >= 0.8:
            prediction = best_match.get("probable_root_cause", current_cause)
            confidence = min(0.95, match_confidence * 0.7 + base_prob * 0.3)
            explanation = f"Crash #{crash_count + 1} is {confidence * 100:.0f}% similar to {best_match.get('report_id')}"
        else:
            prediction = current_cause
            confidence = max(base_prob, 0.3)
            explanation = f"Most frequent root cause in history: {current_cause} ({base_prob * 100:.0f}%)"

        return {
            "ready": True,
            "crash_count": crash_count,
            "prediction": prediction,
            "confidence_percent": round(confidence * 100, 1),
            "explanation": explanation,
            "similar_crashes": similar[:5],
            "historical_distribution": dict(root_causes.most_common(10)),
        }
