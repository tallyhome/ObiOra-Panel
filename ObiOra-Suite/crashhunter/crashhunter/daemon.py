"""Crash Hunter main daemon loop with silent freeze detection."""

from __future__ import annotations

import logging
import signal
import sys
import time
from typing import Any

from crashhunter import __version__
from crashhunter.analysis.rules_engine import RulesEngine
from crashhunter.config.settings import Settings, load_settings
from crashhunter.diagnostics.benchmark import PostRebootBenchmark
from crashhunter.export.panel_bridge import PanelBridge
from crashhunter.export.prometheus import PrometheusExporter
from crashhunter.freeze.detector import SilentFreezeDetector
from crashhunter.freeze.emergency_collector import EmergencyCollector
from crashhunter.freeze.incident_manager import IncidentManager
from crashhunter.kernel.netconsole import NetconsoleManager
from crashhunter.kernel.sysrq import SysRqController
from crashhunter.monitoring.self_monitor import SelfMonitor
from crashhunter.plugins.registry import CollectorRegistry
from crashhunter.report.blackbox import BlackBoxRecorder
from crashhunter.report.event_timeline import EventTimeline
from crashhunter.report.generator import ReportGenerator
from crashhunter.samplers.aggregator import SnapshotAggregator
from crashhunter.storage.incident_store import IncidentStore
from crashhunter.storage.psi_history import PsiHistoryStore
from crashhunter.storage.ring_buffer import RingBuffer
from crashhunter.storage.state_store import StateStore
from crashhunter.utils.logging_setup import setup_logging
from crashhunter.utils.timestamp import now_us
from crashhunter.witness.sender import WitnessSender

logger = logging.getLogger("crashhunter.daemon")


class CrashHunterDaemon:
    """Sampling daemon with silent freeze detection and incident mode."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.settings.ensure_directories()
        self._running = True
        self.state = StateStore(
            settings.boot_id_file,
            settings.last_uptime_file,
            settings.last_clock_file,
        )
        if settings.ring.use_tmpfs:
            RingBuffer.ensure_tmpfs(settings.ring_tmpfs_dir, settings.ring.tmpfs_size_mb)
        self.ring = RingBuffer(
            settings.ring_capacity,
            settings.effective_ring_dir,
            sync_dir=settings.ring_sync_dir,
            defer_disk_writes=settings.ring.use_tmpfs,
        )
        self.ring.load_from_disk()
        self.blackbox = BlackBoxRecorder(self.ring, settings.blackbox_memory_file)
        self.aggregator = SnapshotAggregator(settings)
        self.reporter = ReportGenerator(settings)
        self.timeline = EventTimeline(settings.timeline_file)
        self.detector = SilentFreezeDetector(settings, self.timeline)
        self.incident_store = IncidentStore(settings.incident_dir)
        registry = CollectorRegistry(settings)
        emergency = EmergencyCollector(settings, registry)
        sysrq = SysRqController(settings.sysrq.enabled)
        self.incident_manager = IncidentManager(
            settings, emergency, self.incident_store, self.timeline, sysrq=sysrq,
        )
        self.self_monitor = SelfMonitor(settings)
        self.rules_engine = RulesEngine(settings.rules_path, self.timeline)
        self.prometheus = (
            PrometheusExporter(settings.prometheus_metrics_file)
            if settings.prometheus.enabled else None
        )
        self.witness = WitnessSender(settings)
        self.panel_bridge = PanelBridge(
            settings.panel.url,
            settings.panel.server_id,
            settings.panel.agent_token,
            settings.panel.enabled,
        )
        self._last_panel_push = 0.0
        self.psi_history = PsiHistoryStore(settings.psi_history_file)
        self.benchmark = PostRebootBenchmark(settings)
        self.netconsole = NetconsoleManager(settings)
        self._last_incident_id: str | None = None
        self._last_triggers: list[str] = []
        self._cycle_count = 0
        self._last_ring_sync = time.monotonic()

    def handle_shutdown(self, signum: int, _frame: object) -> None:
        logger.info("Signal %s received, shutting down", signum)
        self._running = False
        if self.settings.ring.use_tmpfs:
            self.ring.sync_to_disk()
        self.psi_history.flush()

    def startup_check(self) -> dict[str, object] | None:
        """Detect reboot and generate post-freeze report if needed."""
        self.incident_manager.load_state()
        if self.settings.netconsole.enabled:
            nc = self.netconsole.configure()
            logger.info("Netconsole: %s", nc)

        reboot_info = self.state.detect_reboot()
        if reboot_info.get("reboot_detected"):
            self.timeline.record(
                "system_reboot_detected",
                f"Reboot detected: {reboot_info.get('reason')}",
                severity="critical",
            )
            logger.warning("Reboot detected (%s) — generating Black Box report", reboot_info.get("reason"))

            if self.benchmark.should_run(True):
                bench = self.benchmark.run()
                reboot_info["post_reboot_benchmark"] = bench
                if bench.get("comparison", {}).get("regressions"):
                    self.timeline.record(
                        "benchmark_regression",
                        "; ".join(bench["comparison"]["regressions"]),
                        severity="warning",
                    )

            incident_summary = None
            if self._last_incident_id:
                incident_summary = {
                    "incident_id": self._last_incident_id,
                    "triggers": self._last_triggers,
                }
            report = self.reporter.generate(
                self.blackbox, reboot_info,
                incident_id=self._last_incident_id,
                timeline=self.timeline,
                incident_summary=incident_summary,
            )
            if self.settings.panel.enabled:
                self.panel_bridge.push_report(report, report.get("bundle_path"))
            reboot_info["report_id"] = report.get("report_id")
            return reboot_info
        return None

    def run_normal_cycle(self) -> dict[str, Any]:
        """Single normal-mode sampling cycle with freeze detection."""
        snapshot = self.aggregator.collect()
        snapshot["ring_count"] = self.ring.count
        self.blackbox.record(snapshot)
        self.state.save_current_state()

        ts = str(snapshot.get("timestamp_us", ""))
        pressure = snapshot.get("pressure", {})
        parsed = pressure.get("parsed") if isinstance(pressure, dict) else None
        self.psi_history.record(ts, parsed)
        snapshot["psi_history"] = self.psi_history.get_trends()

        duration_ms = snapshot.get("collection_duration_ms", 0)
        monitor_snap = self.self_monitor.record_cycle(
            duration_ms,
            self.aggregator.failure_counts,
            self.aggregator.last_durations,
        )

        self.rules_engine.evaluate(snapshot)

        if self.prometheus:
            self.prometheus.export(snapshot, monitor_snap)

        if self.settings.witness.enabled:
            if self.settings.panel.enabled:
                self.panel_bridge.push_witness(
                    self.witness.build_payload(snapshot, incident_mode=False),
                )
            else:
                self.witness.send(snapshot, incident_mode=False)

        if self.settings.panel.enabled:
            self._maybe_push_panel(snapshot)

        signals = self.detector.evaluate(snapshot)
        self._check_live_anomalies(snapshot)

        if self.detector.should_trigger_incident(signals):
            incident_id = self.incident_manager.trigger(signals)
            self._last_incident_id = incident_id
            self._last_triggers = [s.trigger for s in signals]
            self._run_incident_mode()
            report = self.reporter.generate_from_incident(
                self.blackbox, incident_id, self.timeline, self._last_triggers,
            )
            logger.critical("Silent freeze incident report: %s", report.get("report_id"))
            self.detector.reset_timeout_counter()

        return snapshot

    def _run_incident_mode(self) -> None:
        """Emergency mode: 500ms sampling for 60 seconds."""
        interval = self.incident_manager.emergency_interval()
        logger.critical("Entering emergency mode: %.1fs interval for %ds",
                        interval, self.settings.incident.emergency_duration_seconds)
        if self.settings.witness.enabled:
            self.witness.send({"timestamp_us": now_us(), "system": {}}, incident_mode=True)
        while self._running:
            snapshot = self.incident_manager.run_emergency_cycle()
            if snapshot is None:
                break
            self.blackbox.record(snapshot)
            time.sleep(interval)

    def _maybe_push_panel(self, snapshot: dict[str, Any]) -> None:
        import time

        now = time.monotonic()
        if not self.panel_bridge.should_push(now, self.settings.panel.push_interval_seconds):
            return
        snapshot["hostname"] = self.settings.hostname
        self.panel_bridge.push_metrics(snapshot, __version__)
        ordered = self.ring.get_all_ordered()
        batch = ordered[-self.settings.panel.snapshot_batch_size :]
        if batch:
            self.panel_bridge.push_snapshots(batch)
        self.panel_bridge.mark_pushed(now)
        self._last_panel_push = now

    def _sync_ring_buffer(self) -> None:
        if not self.settings.ring.use_tmpfs:
            return
        elapsed = time.monotonic() - self._last_ring_sync
        if elapsed >= self.settings.ring.sync_interval_seconds:
            synced = self.ring.sync_to_disk()
            if synced:
                logger.debug("Ring buffer synced %d slots to disk", synced)
            self._last_ring_sync = time.monotonic()

    def _check_live_anomalies(self, snapshot: dict[str, object]) -> None:
        alerts_path = self.settings.logs_dir / "alerts.log"
        kernel_diff = snapshot.get("kernel", {}).get("dmesg_diff", [])  # type: ignore[union-attr]
        keywords = ("panic", "watchdog", "oom", "hung", "stall", "segfault", "mce", "rcu")
        for line in kernel_diff:
            if any(k in line.lower() for k in keywords):
                ts = now_us()
                try:
                    with alerts_path.open("a", encoding="utf-8") as fh:
                        fh.write(f"{ts} ALERT kernel anomaly: {line[:200]}\n")
                except OSError:
                    pass

    def run(self) -> int:
        """Main daemon loop."""
        signal.signal(signal.SIGTERM, self.handle_shutdown)
        signal.signal(signal.SIGINT, self.handle_shutdown)

        setup_logging(self.settings.log_level, self.settings.logs_dir / "daemon.log")
        logger.info("CrashHunter Enterprise v%s on %s", __version__, self.settings.hostname)

        self.startup_check()

        while self._running:
            if self.incident_manager.is_incident:
                snapshot = self.incident_manager.run_emergency_cycle()
                if snapshot:
                    self.blackbox.record(snapshot)
                time.sleep(self.incident_manager.emergency_interval())
                continue

            cycle_start = time.monotonic()
            try:
                self.run_normal_cycle()
            except Exception as exc:
                logger.exception("Sampling cycle failed: %s", exc)
            elapsed = time.monotonic() - cycle_start
            sleep_time = max(0.0, self.settings.interval_seconds - elapsed)
            if sleep_time > 0:
                time.sleep(sleep_time)

            self._cycle_count += 1
            self._sync_ring_buffer()
            if self._cycle_count % 12 == 0:
                self.psi_history.flush()

            if self._cycle_count % 720 == 0 and self.settings.retention.enabled:
                from crashhunter.storage.retention import RetentionManager
                RetentionManager(
                    self.settings.reports_dir,
                    self.settings.retention.retention_days,
                    self.settings.retention.compress_after_days,
                ).run()

        logger.info("CrashHunter Enterprise stopped")
        return 0


def main() -> int:
    settings = load_settings()
    daemon = CrashHunterDaemon(settings)
    return daemon.run()


if __name__ == "__main__":
    sys.exit(main())
