"""Push diagnostic reports to ObiOra Panel central API."""

from __future__ import annotations

import json
import urllib.error
import urllib.request
from typing import Any


def push_report_to_panel(
    panel_url: str,
    server_id: int | str,
    agent_token: str,
    report_dict: dict[str, Any],
    *,
    timeout: int = 30,
) -> dict[str, Any]:
    """POST a diagnostic report to ObiOra Panel.

    Parameters:
        panel_url: Base URL e.g. https://panel.example.com
        server_id: Server ID registered in panel
        agent_token: Bearer token from panel server settings
        report_dict: Complete report JSON
        timeout: HTTP timeout seconds

    Returns:
        API response dictionary.

    Example:
        push_report_to_panel("https://panel.local", 2, "token...", report.to_dict())
    """

    url = f"{panel_url.rstrip('/')}/api/v1/servers/{server_id}/diagnostics/reports"
    body = json.dumps(report_dict, ensure_ascii=False).encode("utf-8")
    request = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "Authorization": f"Bearer {agent_token}",
            "Content-Type": "application/json",
            "User-Agent": "Obiora-Agent/0.4.0",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="ignore")
        return {"ok": False, "status": exc.code, "error": detail}
    except urllib.error.URLError as exc:
        return {"ok": False, "error": str(exc.reason)}


def push_ping_to_panel(
    panel_url: str,
    server_id: int | str,
    agent_token: str,
    metrics: dict[str, Any],
) -> dict[str, Any]:
    """POST lightweight heartbeat metrics to panel."""

    url = f"{panel_url.rstrip('/')}/api/v1/servers/{server_id}/diagnostics/heartbeat"
    body = json.dumps(metrics, ensure_ascii=False).encode("utf-8")
    request = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "Authorization": f"Bearer {agent_token}",
            "Content-Type": "application/json",
            "User-Agent": "Obiora-Agent/0.4.0",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=15) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        return {"ok": False, "status": exc.code, "error": exc.read().decode("utf-8", errors="ignore")}
    except urllib.error.URLError as exc:
        return {"ok": False, "error": str(exc.reason)}
