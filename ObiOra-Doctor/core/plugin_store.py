"""Remote plugin catalog and installation for Obiora Doctor."""

from __future__ import annotations

import json
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

DEFAULT_CATALOG_URL = (
    "https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/"
    "ObiOra-Doctor/config/plugin-catalog.json"
)
PLUGINS_DIR = Path(__file__).resolve().parents[1] / "plugins"


def load_catalog(catalog_path: Path | str | None = None) -> list[dict[str, Any]]:
    """Load plugin catalog from local file or remote URL."""

    if catalog_path is None:
        catalog_path = Path(__file__).resolve().parents[1] / "config" / "plugin-catalog.json"
    path = Path(catalog_path)
    if path.exists():
        with path.open(encoding="utf-8") as handle:
            data = json.load(handle)
        return data.get("plugins", data if isinstance(data, list) else [])

    try:
        with urllib.request.urlopen(str(catalog_path), timeout=15) as response:
            data = json.loads(response.read().decode("utf-8"))
        return data.get("plugins", [])
    except (urllib.error.URLError, json.JSONDecodeError, TimeoutError):
        return []


def install_plugin_from_catalog(plugin_id: str, catalog: list[dict[str, Any]] | None = None) -> Path:
    """Download a plugin Python file from catalog into plugins directory."""

    catalog = catalog if catalog is not None else load_catalog()
    entry = next((item for item in catalog if item.get("id") == plugin_id), None)
    if entry is None:
        raise ValueError(f"Plugin inconnu: {plugin_id}")

    url = entry.get("download_url") or entry.get("url")
    if not url:
        raise ValueError(f"URL manquante pour {plugin_id}")

    PLUGINS_DIR.mkdir(parents=True, exist_ok=True)
    filename = entry.get("filename") or f"{plugin_id}.py"
    target = PLUGINS_DIR / filename

    with urllib.request.urlopen(str(url), timeout=30) as response:
        target.write_bytes(response.read())

    return target


def list_installed_plugins() -> list[str]:
    """List plugin module filenames in plugins directory."""

    if not PLUGINS_DIR.exists():
        return []
    return sorted(path.name for path in PLUGINS_DIR.glob("*.py") if not path.name.startswith("_"))
