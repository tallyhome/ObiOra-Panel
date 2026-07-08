"""Dynamic plugin loader for Obiora Doctor."""

from __future__ import annotations

import importlib.util
import inspect
import sys
from pathlib import Path

from core.module import DiagnosticModule

PLUGINS_DIR = Path(__file__).resolve().parents[1] / "plugins"


def discover_plugins(plugins_dir: Path | None = None) -> list[type[DiagnosticModule]]:
    """Discover DiagnosticModule subclasses in plugins directory.

    Parameters:
        plugins_dir: Optional plugins directory path.

    Returns:
        List of plugin module classes.

    Example:
        plugins = discover_plugins()
    """

    directory = plugins_dir or PLUGINS_DIR
    if not directory.exists():
        return []

    discovered: list[type[DiagnosticModule]] = []
    for file_path in sorted(directory.glob("*.py")):
        if file_path.name.startswith("_"):
            continue
        module = _load_module(file_path)
        if module is None:
            continue
        for _, obj in inspect.getmembers(module, inspect.isclass):
            if (
                issubclass(obj, DiagnosticModule)
                and obj is not DiagnosticModule
                and obj.__module__ == module.__name__
            ):
                discovered.append(obj)
    return discovered


def _load_module(file_path: Path):
    """Load a Python module from file path."""

    module_name = f"obiora_plugin_{file_path.stem}"
    spec = importlib.util.spec_from_file_location(module_name, file_path)
    if spec is None or spec.loader is None:
        return None
    module = importlib.util.module_from_spec(spec)
    sys.modules[module_name] = module
    try:
        spec.loader.exec_module(module)
    except Exception:
        return None
    return module
