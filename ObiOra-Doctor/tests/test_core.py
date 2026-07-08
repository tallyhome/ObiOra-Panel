"""Core tests for Obiora Doctor."""

from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from core.bench import run_full_benchmark
from core.compare import compare_reports
from core.config import load_config
from core.engine import DiagnosticEngine
from core.knowledge import enrich_report
from core.models import Finding, ModuleResult, Report, Severity
from core.redact import redact_report_dict, redact_text
from core.reports import render_json, write_report_bundle
from core.rescue import generate_rescue_plan
from core.runner import CommandRunner
from core.schema import validate_report
from modules.registry import module_names


class CoreTestCase(unittest.TestCase):
    """Validate core scoring, runner and reports."""

    def test_global_score_uses_average(self) -> None:
        """The global score is the rounded average of module scores."""

        self.assertEqual(DiagnosticEngine.global_score([100, 80, 60]), 80)

    def test_missing_command_is_structured(self) -> None:
        """A missing executable must not raise an exception."""

        result = CommandRunner().run(["obiora-command-that-does-not-exist"])

        self.assertTrue(result.missing)
        self.assertFalse(result.ok)
        self.assertIsNone(result.returncode)

    def test_report_json_is_serializable(self) -> None:
        """Report JSON output can be loaded by machines."""

        report = self._sample_report()
        payload = json.loads(render_json(report))

        self.assertEqual(payload["score"], 100)
        self.assertEqual(payload["results"][0]["findings"][0]["level"], "INFO")

    def test_report_bundle_writes_all_formats(self) -> None:
        """A scan writes JSON, Markdown, HTML and text reports."""

        with tempfile.TemporaryDirectory() as directory:
            output_dir = write_report_bundle(self._sample_report(), Path(directory))

            self.assertTrue((output_dir / "report.json").exists())
            self.assertTrue((output_dir / "report.md").exists())
            self.assertTrue((output_dir / "report.html").exists())
            self.assertTrue((output_dir / "report.txt").exists())

    def test_redact_text_masks_ip(self) -> None:
        """Support mode redacts public IPs."""

        result = redact_text("Serveur 203.0.113.10 actif")
        self.assertIn("[IP_REDACTED]", result)
        self.assertNotIn("203.0.113.10", result)

    def test_redact_report_dict_anonymizes_host(self) -> None:
        """Support mode redacts hostname in JSON."""

        payload = redact_report_dict(self._sample_report().to_dict())
        self.assertEqual(payload["host"]["hostname"], "[HOST_REDACTED]")
        self.assertTrue(payload["anonymized"])

    def test_compare_reports_detects_score_change(self) -> None:
        """Comparison highlights module score changes."""

        left = self._sample_report().to_dict()
        right = self._sample_report().to_dict()
        right["results"][0]["score"] = 70
        right["score"] = 70
        diff = compare_reports(left, right)
        self.assertEqual(diff["score_delta"], -30)

    def test_compare_reports_detects_metric_changes(self) -> None:
        """Comparison includes metric diffs."""

        left = self._sample_report().to_dict()
        right = self._sample_report().to_dict()
        right["results"][0]["metrics"] = {"load": 1.0}
        diff = compare_reports(left, right)
        self.assertTrue(diff["metrics"])
        self.assertEqual(diff["metrics"][0]["metric"], "load")

    def test_sign_and_verify_report(self) -> None:
        """HMAC signature roundtrip works."""

        from core.signing import sign_report_dict, verify_report_dict

        payload = self._sample_report().to_dict()
        signed = sign_report_dict(payload)
        self.assertIn("signature", signed)
        self.assertTrue(verify_report_dict(signed))

    def test_ssl_days_until_expiry_parsing(self) -> None:
        """SSL module parses expiry into days."""

        from core.runner import CommandRunner
        from modules.ssl import SslModule

        module = SslModule(CommandRunner())
        days = module._days_until_expiry("Jan  1 2030 00:00:00 GMT")
        self.assertIsInstance(days, int)
        self.assertGreater(days, 0)

    def test_config_loads_defaults(self) -> None:
        """Configuration provides required keys."""

        config = load_config()
        self.assertIn("timeout_seconds", config)
        self.assertIn("reports_dir", config)

    def test_builtin_modules_registered(self) -> None:
        """All expected modules are registered."""

        names = module_names()
        self.assertIn("cpu", names)
        self.assertIn("virtualizor", names)
        self.assertIn("laravel", names)
        self.assertIn("cpanel", names)
        self.assertGreaterEqual(len(names), 26)

    def test_knowledge_enriches_report(self) -> None:
        """Knowledge base adds probable cause to findings."""

        payload = enrich_report(self._sample_report().to_dict())
        enriched = payload["results"][0].get("findings_enriched", [])
        self.assertTrue(enriched)

    def test_schema_validation_passes(self) -> None:
        """Valid report passes schema validation."""

        errors = validate_report(self._sample_report().to_dict())
        self.assertEqual(errors, [])

    def test_rescue_plan_generated(self) -> None:
        """Rescue plan is generated without errors."""

        plan = generate_rescue_plan(self._sample_report())
        self.assertIn("OBIORA RESCUE", plan)

    def test_reboot_module_in_registry(self) -> None:
        """Reboot module is registered."""

        self.assertIn("reboot", module_names())

    def test_reboot_analyze_returns_structure(self) -> None:
        """24h reboot analysis returns expected keys."""

        from core.reboot_monitor import analyze_last_24h

        analysis = analyze_last_24h(CommandRunner())
        self.assertIn("probable_causes", analysis)
        self.assertIn("next_reboot_risk", analysis)

    @staticmethod
    def _sample_report() -> Report:
        """Build a minimal report for tests."""

        return Report(
            version="0.1.0",
            generated_at="2026-07-07T18-20-00Z",
            host={"hostname": "test-host", "platform": "linux", "system": "Linux"},
            score=100,
            results=[
                ModuleResult(
                    module="cpu",
                    status="ok",
                    score=100,
                    findings=[
                        Finding(
                            Severity.INFO,
                            "CPU detecte",
                            "Inventaire CPU collecte.",
                        )
                    ],
                )
            ],
        )


if __name__ == "__main__":
    unittest.main()
