"""Crash report generator — Enterprise orchestration."""

from __future__ import annotations

import logging
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from crashhunter import __version__
from crashhunter.analysis.correlation import CorrelationEngine
from crashhunter.analysis.ml_similarity import CrashLearningEngine
from crashhunter.analysis.reboot_classifier import RebootClassifier
from crashhunter.analysis.recommendations import RecommendationsEngine
from crashhunter.analysis.regression import RegressionDetector
from crashhunter.config.settings import Settings
from crashhunter.report.blackbox import BlackBoxRecorder
from crashhunter.report.chronological import ChronologicalReportBuilder
from crashhunter.report.diagnosis import DiagnosisEngine
from crashhunter.report.event_timeline import EventTimeline
from crashhunter.report.exporters.bundle_export import export_bundle
from crashhunter.report.exporters.html_export import export_html
from crashhunter.report.exporters.json_export import export_json
from crashhunter.report.exporters.markdown_export import export_markdown
from crashhunter.report.exporters.pdf_export import export_pdf
from crashhunter.report.similarity import SimilarityEngine
from crashhunter.report.timeline import build_metric_series
from crashhunter.storage.incident_store import IncidentStore
from crashhunter.storage.retention import RetentionManager
from crashhunter.utils.subprocess_runner import SubprocessRunner
from crashhunter.utils.version_signature import collect_version_signature

logger = logging.getLogger("crashhunter.report")


class ReportGenerator:
    """Generate CrashReport with correlation, regression, recommendations and bundle."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.diagnosis = DiagnosisEngine()
        self.similarity = SimilarityEngine(settings)
        self.ml_learning = CrashLearningEngine(self.similarity, settings.similarity.min_crashes_for_ml)
        self.chronological = ChronologicalReportBuilder()
        self.correlation = CorrelationEngine()
        self.reboot_classifier = RebootClassifier()
        self.recommendations = RecommendationsEngine()
        self.regression = RegressionDetector(settings.regression_state_file)
        self.incident_store = IncidentStore(settings.incident_dir)

    def generate(
        self,
        blackbox: BlackBoxRecorder,
        reboot_info: dict[str, object],
        incident_id: str | None = None,
        timeline: EventTimeline | None = None,
        incident_summary: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        report_id = datetime.now().strftime("CrashReport_%Y%m%d_%H%M%S")
        correlation_data = blackbox.correlate()
        diagnosis = self.diagnosis.analyze(correlation_data)
        metrics = build_metric_series(correlation_data)

        events = timeline.get_events() if timeline else []
        causal = self.correlation.correlate(events)
        reboot_class = self.reboot_classifier.classify(reboot_info)

        runner = SubprocessRunner(default_timeout=3.0)
        version_sig = collect_version_signature(runner)
        regression = self.regression.check(version_sig)

        similar = self.similarity.find_similar({
            "report_id": report_id,
            "diagnosis": diagnosis,
            "blackbox": correlation_data,
        })
        ml_prediction = self.ml_learning.predict({
            "report_id": report_id,
            "diagnosis": diagnosis,
            "blackbox": correlation_data,
        })
        chronological = self.chronological.build(events, incident_summary, diagnosis, similar)
        chronological["causal_story"] = causal.get("story_text", "")
        recommendations = self.recommendations.generate(diagnosis, reboot_class)

        if incident_id:
            incident_data = {
                "incident_id": incident_id,
                "triggers": incident_summary.get("triggers", []) if incident_summary else [],
                "emergency_snapshots": self.incident_store.load_incident(incident_id),
                "summary": incident_summary,
            }
        else:
            incident_data = {"triggers": [], "emergency_snapshots": []}

        report: dict[str, Any] = {
            "report_id": report_id,
            "crashhunter_version": __version__,
            "edition": "enterprise",
            "hostname": self.settings.hostname,
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "version_signature": version_sig,
            "reboot_detection": reboot_info,
            "reboot_classification": reboot_class,
            "regression_analysis": regression,
            "incident": incident_data,
            "blackbox": correlation_data,
            "diagnosis": diagnosis,
            "metrics": metrics,
            "event_timeline": events,
            "causal_correlation": causal,
            "chronological_report": chronological,
            "similar_crashes": similar,
            "ml_prediction": ml_prediction,
            "recommendations": recommendations,
        }

        self.similarity.index_report(report)

        base = self.settings.reports_dir / report_id
        base.mkdir(parents=True, exist_ok=True)

        export_json(report, base / f"{report_id}.json")
        export_html(report, base / f"{report_id}.html")
        export_markdown(report, base / f"{report_id}.md")
        try:
            export_pdf(report, base / f"{report_id}.pdf")
        except Exception as exc:
            logger.error("PDF export failed: %s", exc)

        bundle_path = export_bundle(report, base, self.settings.base_dir)
        report["bundle_path"] = str(bundle_path)

        if self.settings.retention.enabled:
            RetentionManager(
                self.settings.reports_dir,
                self.settings.retention.retention_days,
                self.settings.retention.compress_after_days,
            ).run()

        logger.info("Report generated: %s (bundle: %s)", base, bundle_path)
        return report

    def generate_from_incident(
        self,
        blackbox: BlackBoxRecorder,
        incident_id: str,
        timeline: EventTimeline,
        triggers: list[str],
    ) -> dict[str, Any]:
        summary = {
            "incident_id": incident_id,
            "triggers": triggers,
            "snapshot_count": self.incident_store.count(incident_id),
            "started_at": datetime.now(timezone.utc).isoformat(),
            "ended_at": datetime.now(timezone.utc).isoformat(),
        }
        reboot_info = {
            "reboot_detected": False,
            "reason": "silent_freeze_incident",
            "incident_id": incident_id,
        }
        return self.generate(
            blackbox, reboot_info,
            incident_id=incident_id,
            timeline=timeline,
            incident_summary=summary,
        )
