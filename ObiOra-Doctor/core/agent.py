"""Obiora Agent with optional push to ObiOra Panel."""

from __future__ import annotations

import json
import socket
import time
from pathlib import Path
from typing import Any

from core.engine import DiagnosticEngine
from core.knowledge import enrich_report
from core.panel_client import push_ping_to_panel, push_report_to_panel
from core.reports import write_report_bundle
from core.signing import sign_report_dict


def load_panel_config(config_path: Path | None = None) -> dict[str, Any]:
    """Load panel push configuration."""

    path = config_path or Path(__file__).resolve().parents[1] / "config" / "agent-panel.json"
    if not path.exists():
        return {}
    with path.open(encoding="utf-8") as handle:
        return json.load(handle)


def run_agent(
    engine: DiagnosticEngine,
    *,
    interval: int = 300,
    cache_dir: str = "cache",
    reports_dir: str = "reports",
    once: bool = False,
    panel_config: dict[str, Any] | None = None,
) -> int:
    """Run periodic scans and optionally push to ObiOra Panel."""

    panel = panel_config or load_panel_config()
    agent_dir = Path(cache_dir) / "agent"
    agent_dir.mkdir(parents=True, exist_ok=True)
    heartbeat = agent_dir / "heartbeat.json"

    print("Obiora Agent - intervalle {}s".format(interval))
    if panel.get("panel_url"):
        print("Push panel: {} (server #{})".format(panel["panel_url"], panel.get("server_id")))

    try:
        while True:
            report = engine.run()
            output = write_report_bundle(report, Path(reports_dir))
            report_dict = sign_report_dict(enrich_report(report.to_dict()))

            state: dict[str, Any] = {
                "status": "online",
                "last_scan": report.generated_at,
                "score": report.score,
                "report_path": str(output),
                "modules_run": len(report.results),
                "hostname": socket.gethostname(),
            }

            if panel.get("panel_url") and panel.get("agent_token") and panel.get("server_id"):
                push_result = push_report_to_panel(
                    panel["panel_url"],
                    panel["server_id"],
                    panel["agent_token"],
                    report_dict,
                )
                state["panel_push"] = push_result
                push_ping_to_panel(
                    panel["panel_url"],
                    panel["server_id"],
                    panel["agent_token"],
                    {
                        "score": report.score,
                        "hostname": socket.gethostname(),
                        "online": True,
                    },
                )

            heartbeat.write_text(json.dumps(state, indent=2, ensure_ascii=False), encoding="utf-8")
            print(f"Scan OK - score {report.score}% -> {output}")
            if once:
                return 0
            time.sleep(interval)
    except KeyboardInterrupt:
        print("\nAgent arrete.")
        return 0
