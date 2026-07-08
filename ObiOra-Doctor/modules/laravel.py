"""Laravel application diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule

LARAVEL_PATHS = [
    Path("/var/www"),
    Path("/home"),
]


class LaravelModule(DiagnosticModule):
    """Detect Laravel apps and basic configuration risks."""

    name = "laravel"
    title = "Laravel"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Scan common paths for Laravel installations."""

        apps: list[dict[str, str]] = []
        for base in LARAVEL_PATHS:
            if not base.exists():
                continue
            for artisan in base.rglob("artisan"):
                app_dir = artisan.parent
                if len(apps) >= 10:
                    break
                env_path = app_dir / ".env"
                apps.append(
                    {
                        "path": str(app_dir),
                        "has_env": env_path.exists(),
                        "debug_on": self._debug_enabled(env_path),
                    }
                )
        return {"metrics": {"app_count": len(apps), "apps": apps}}

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build Laravel findings."""

        apps = raw_data["metrics"]["apps"]
        if not apps:
            return [
                Finding(
                    Severity.INFO,
                    "Laravel non detecte",
                    "Aucune application Laravel trouvee dans les chemins standards.",
                    "Aucune action requise.",
                )
            ]

        findings = [
            Finding(
                Severity.INFO,
                "Applications Laravel detectees",
                f"{len(apps)} application(s) trouvee(s).",
                "Verifier les caches et les permissions storage.",
                ["php artisan about"],
            )
        ]
        debug_apps = [app["path"] for app in apps if app.get("debug_on")]
        if debug_apps:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "APP_DEBUG active",
                    "Applications avec debug: " + ", ".join(debug_apps[:3]),
                    "Desactiver APP_DEBUG en production.",
                    ["grep APP_DEBUG .env"],
                )
            )
        return findings

    @staticmethod
    def _debug_enabled(env_path: Path) -> bool:
        """Return True when APP_DEBUG=true in .env."""

        if not env_path.exists():
            return False
        try:
            content = env_path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            return False
        for line in content.splitlines():
            if line.strip().startswith("APP_DEBUG=true"):
                return True
        return False
