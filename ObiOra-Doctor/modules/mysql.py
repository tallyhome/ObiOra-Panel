"""MySQL and MariaDB diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import service_finding, systemd_active


class MysqlModule(DiagnosticModule):
    """Collect and diagnose MySQL/MariaDB state."""

    name = "mysql"
    title = "MySQL"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect MySQL/MariaDB service and version data."""

        mysql_active, mysql_detail = systemd_active(self.runner, "mysqld")
        mariadb_active, mariadb_detail = systemd_active(self.runner, "mariadb")
        version = self.runner.run(["mysql", "--version"])
        slow_queries = self._collect_slow_query_stats()
        return {
            "mysql_active": mysql_active,
            "mariadb_active": mariadb_active,
            "version": version.to_dict(),
            "slow_queries": slow_queries,
            "metrics": {
                "service_active": mysql_active or mariadb_active,
                "engine": "mariadb" if mariadb_active else "mysql" if mysql_active else None,
                "client_available": not version.missing,
                "slow_queries_total": slow_queries.get("slow_queries_total"),
                "long_query_time_sec": slow_queries.get("long_query_time_sec"),
                "slow_query_log_enabled": slow_queries.get("slow_query_log_enabled"),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build MySQL/MariaDB findings."""

        metrics = raw_data["metrics"]
        if not metrics["service_active"] and not metrics["client_available"]:
            return [
                Finding(
                    Severity.INFO,
                    "MySQL/MariaDB non detecte",
                    "Aucun service ou client MySQL detecte.",
                    "Aucune action requise si SQL n'est pas utilise.",
                    ["systemctl status mysqld", "systemctl status mariadb"],
                )
            ]

        findings: list[Finding] = []
        if metrics["service_active"]:
            engine = metrics["engine"] or "mysql"
            findings.append(service_finding(engine, True, f"Service {engine} actif."))
        else:
            findings.append(
                service_finding("mysql", False, "Service SQL inactif.", optional=True)
            )

        if raw_data["version"]["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Version SQL",
                    raw_data["version"]["stdout"],
                    "Verifier les mises a jour de securite.",
                    ["mysql --version"],
                )
            )

        slow = raw_data.get("slow_queries", {})
        total = slow.get("slow_queries_total")
        if isinstance(total, int):
            if total > 1000:
                findings.append(
                    Finding(
                        Severity.CRITICAL,
                        "Requetes lentes elevees",
                        f"Slow_queries={total} depuis le demarrage.",
                        "Analyser slow_query_log et optimiser les requetes.",
                        ["mysql -e \"SHOW GLOBAL STATUS LIKE 'Slow_queries';\"", "mysqldumpslow /var/log/mysql/slow.log"],
                    )
                )
            elif total > 100:
                findings.append(
                    Finding(
                        Severity.WARNING,
                        "Requetes lentes detectees",
                        f"Slow_queries={total} depuis le demarrage.",
                        "Verifier les index et le slow query log.",
                        ["mysql -e \"SHOW VARIABLES LIKE 'slow_query_log';\"", "mysql -e \"SHOW GLOBAL STATUS LIKE 'Slow_queries';\""],
                    )
                )
            else:
                findings.append(
                    Finding(
                        Severity.INFO,
                        "Slow queries",
                        f"Slow_queries={total}.",
                        "Surveiller si la charge augmente.",
                        ["mysql -e \"SHOW GLOBAL STATUS LIKE 'Slow_queries';\""],
                    )
                )

        if slow.get("slow_query_log_enabled") is False:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Slow query log desactive",
                    "Le journal des requetes lentes n'est pas actif.",
                    "Activer slow_query_log pour le diagnostic detaille.",
                    ["mysql -e \"SET GLOBAL slow_query_log = 'ON';\""],
                )
            )

        return findings

    def _collect_slow_query_stats(self) -> dict[str, Any]:
        """Read global slow query counters when mysql client is available."""

        status = self.runner.run(
            ["mysql", "-N", "-e", "SHOW GLOBAL STATUS LIKE 'Slow_queries';"],
            timeout_seconds=8,
        )
        slow_total = None
        if status.ok:
            parts = status.stdout.strip().split()
            if len(parts) >= 2 and parts[1].isdigit():
                slow_total = int(parts[1])

        long_query = self.runner.run(
            ["mysql", "-N", "-e", "SHOW VARIABLES LIKE 'long_query_time';"],
            timeout_seconds=8,
        )
        long_query_time = None
        if long_query.ok:
            parts = long_query.stdout.strip().split()
            if len(parts) >= 2:
                try:
                    long_query_time = float(parts[1])
                except ValueError:
                    long_query_time = None

        slow_log = self.runner.run(
            ["mysql", "-N", "-e", "SHOW VARIABLES LIKE 'slow_query_log';"],
            timeout_seconds=8,
        )
        slow_log_enabled = None
        if slow_log.ok:
            parts = slow_log.stdout.strip().split()
            if len(parts) >= 2:
                slow_log_enabled = parts[1].upper() in {"ON", "1", "YES"}

        return {
            "slow_queries_total": slow_total,
            "long_query_time_sec": long_query_time,
            "slow_query_log_enabled": slow_log_enabled,
        }
