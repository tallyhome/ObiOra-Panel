"""Daemon principal Crash Analyzer."""

from __future__ import annotations

import json
import logging
import signal
import socket
import time
import urllib.error
import urllib.request
from typing import Any

from crash_analyzer.boot_journal import collect_boot_journal_snapshot
from crash_analyzer.collectors import build_collectors
from crash_analyzer.config import CrashAnalyzerConfig
from crash_analyzer.detectors import EventDetector
from crash_analyzer.reporters import ReportGenerator
from crash_analyzer.storage import create_storage

logger = logging.getLogger("crash_analyzer")


class CrashAnalyzerDaemon:
    """Boucle de surveillance toutes les N secondes."""

    def __init__(self, config: CrashAnalyzerConfig) -> None:
        self.config = config
        self.storage = create_storage(
            config.storage_backend,
            config.sqlite_path,
            config.postgresql_dsn,
        )
        self.storage.initialize()
        self.collectors = build_collectors(config.enabled_collectors)
        self.detector = EventDetector(config.state_file)
        self.reporter = ReportGenerator(config.reports_dir, config.history_minutes)
        self._running = True
        self._last_push = 0.0
        self._cycle = 0
        self._hostname = socket.gethostname()

    def run(self) -> None:
        signal.signal(signal.SIGTERM, self._handle_signal)
        signal.signal(signal.SIGINT, self._handle_signal)

        load_collector = next((c for c in self.collectors if c.name == "load"), None)
        boot_id, uptime = "", 0.0
        if load_collector:
            load_data = load_collector.collect()
            boot_id = str(load_data.get("boot_id", ""))
            uptime = float(load_data.get("uptime_seconds", 0))

        reboot_event = self.detector.check_unexpected_reboot(boot_id, uptime)
        if reboot_event:
            logger.warning("Redémarrage inattendu: %s", reboot_event.title)
            self.detector.persist_events(self.storage, [reboot_event])
            boot_journal = collect_boot_journal_snapshot()
            self.storage.insert_metric("journal_boot", boot_journal, time.time())
            report = self.reporter.generate(
                self.storage,
                self._hostname,
                trigger_event={
                    "event_type": reboot_event.event_type,
                    "title": reboot_event.title,
                    "details": reboot_event.details,
                },
                extras={"boot_journal": boot_journal},
            )
            self._push_crash_report(report)

        logger.info(
            "Crash Analyzer démarré — intervalle %ds, rétention %d min, %d collecteurs",
            self.config.interval_seconds,
            self.config.history_minutes,
            len(self.collectors),
        )

        while self._running:
            cycle_start = time.monotonic()
            try:
                self._tick()
            except Exception:
                logger.exception("Erreur cycle %d", self._cycle)
            elapsed = time.monotonic() - cycle_start
            sleep_time = max(0.1, self.config.interval_seconds - elapsed)
            time.sleep(sleep_time)
            self._cycle += 1

        self.detector.mark_graceful_shutdown()
        logger.info("Crash Analyzer arrêté proprement")

    def _tick(self) -> None:
        sampled_at = time.time()
        batch: dict[str, dict[str, Any]] = {}

        for collector in self.collectors:
            try:
                data = collector.collect()
                batch[collector.name] = data
                self.storage.insert_metric(collector.name, data, sampled_at)
            except Exception:
                logger.debug("Collecteur %s en échec", collector.name, exc_info=True)

        log_events = self.detector.scan_logs()
        metric_events = self.detector.scan_metrics(batch)
        all_events = log_events + metric_events
        if all_events:
            self.detector.persist_events(self.storage, all_events)
            for event in all_events:
                if event.severity == "critical":
                    logger.warning("Événement critique: %s — %s", event.event_type, event.title)
                    report = self.reporter.generate(
                        self.storage,
                        self._hostname,
                        trigger_event={
                            "event_type": event.event_type,
                            "title": event.title,
                            "details": event.details,
                        },
                    )
                    self._push_crash_report(report)

        if sampled_at - self._last_push >= self.config.push_interval_seconds:
            self._push_metrics_batch(batch, sampled_at)
            self._last_push = sampled_at

        if self._cycle % 60 == 0:
            pruned = self.storage.prune_old(self.config.retention_seconds())
            if pruned:
                logger.debug("Purge: %d entrées supprimées", pruned)

    def _push_metrics_batch(self, batch: dict[str, dict[str, Any]], sampled_at: float) -> None:
        if not self.config.panel_url or not self.config.agent_token:
            return
        payload = {
            "sampled_at": sampled_at,
            "hostname": self._hostname,
            "metrics": batch,
            "events": self.storage.recent_events(20),
        }
        self._api_post("crash-analyzer/metrics", payload)

    def _push_crash_report(self, report: dict[str, Any]) -> None:
        if not self.config.panel_url or not self.config.agent_token:
            logger.info("Rapport local: %s", report.get("directory"))
            return
        payload = {
            "report_id": report["report_id"],
            "hostname": self._hostname,
            "generated_at": report["payload"]["generated_at"],
            "trigger_event": report["payload"].get("trigger_event"),
            "report_json": report["payload"],
            "pdf_base64": report.get("pdf_base64"),
        }
        self._api_post("crash-analyzer/reports", payload)

    def _api_post(self, path: str, payload: dict[str, Any]) -> None:
        url = f"{self.config.panel_url.rstrip('/')}/api/v1/servers/{self.config.server_id}/{path}"
        data = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(
            url,
            data=data,
            headers={
                "Authorization": f"Bearer {self.config.agent_token}",
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=15) as resp:
                logger.debug("API %s → HTTP %s", path, resp.status)
        except urllib.error.HTTPError as exc:
            logger.error("API %s → HTTP %s: %s", path, exc.code, exc.read()[:200])
        except urllib.error.URLError as exc:
            logger.error("API %s inaccessible: %s", path, exc.reason)

    def _handle_signal(self, signum: int, _frame: Any) -> None:
        logger.info("Signal %s reçu", signum)
        self._running = False
