"""Command line interface for Obiora Doctor / Obiora Suite."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

from core.agent import run_agent
from core.api import serve_api
from core.bench import render_bench_text, run_full_benchmark
from core.compare import compare_reports, load_report, render_compare_text
from core.config import load_config
from core.engine import DiagnosticEngine, VERSION
from core.history import clean_old_reports, list_reports
from core.launcher import run_launcher
from core.logging_util import setup_logging
from core.reboot_monitor import (
    analyze_last_24h,
    render_reboot_report_text,
    run_reboot_watch,
)
from core.reports import export_report_zip, render_json, write_report_bundle
from core.rescue import generate_rescue_plan
from core.terminal import print_summary
from core.tui import run_interactive
from core.watch import run_watch
from core.web_secure import serve_web
from modules.registry import enabled_modules, module_names


def build_parser() -> argparse.ArgumentParser:
    """Build the CLI argument parser."""

    parser = argparse.ArgumentParser(
        prog="obiora-doctor",
        description="Obiora Suite - Diagnostic Linux et Virtualizor professionnel.",
    )
    parser.add_argument("--version", action="version", version=f"%(prog)s {VERSION}")

    subparsers = parser.add_subparsers(dest="command")

    scan = subparsers.add_parser("scan", help="Lancer un diagnostic complet.")
    scan.add_argument("--module", action="append", choices=module_names())
    scan.add_argument("--exclude-module", action="append", choices=module_names())
    scan.add_argument("--reports-dir", default=None)
    scan.add_argument("--no-color", action="store_true")
    scan.add_argument("--support", action="store_true", help="Rapport anonymise.")
    scan.add_argument("--json", action="store_true", help="Sortie JSON sur stdout.")
    scan.add_argument("--quiet", action="store_true")
    scan.add_argument("--verbose", action="store_true")
    scan.add_argument("--zip", action="store_true", help="Exporter en zip.")

    subparsers.add_parser("list-modules", help="Lister les modules disponibles.")

    interactive = subparsers.add_parser("interactive", help="Menu interactif.")
    interactive.add_argument("--reports-dir", default=None)
    interactive.add_argument("--no-color", action="store_true")

    watch = subparsers.add_parser("watch", help="Monitoring temps reel.")
    watch.add_argument("--module", action="append", choices=module_names())
    watch.add_argument("--interval", type=float, default=None)
    watch.add_argument("--no-color", action="store_true")

    compare = subparsers.add_parser("compare", help="Comparer deux rapports.")
    compare.add_argument("left")
    compare.add_argument("right")

    history = subparsers.add_parser("history", help="Historique des rapports.")
    history.add_argument("--reports-dir", default=None)

    clean = subparsers.add_parser("clean", help="Supprimer les anciens rapports.")
    clean.add_argument("--days", type=int, default=None)
    clean.add_argument("--reports-dir", default=None)

    api = subparsers.add_parser("api", help="API REST locale lecture seule.")
    api.add_argument("--host", default=None)
    api.add_argument("--port", type=int, default=None)
    api.add_argument("--reports-dir", default=None)

    bench = subparsers.add_parser("bench", help="Benchmarks CPU/RAM/disque/reseau.")
    agent = subparsers.add_parser("agent", help="Agent local de diagnostic.")
    agent.add_argument("--interval", type=int, default=300)
    agent.add_argument("--once", action="store_true")
    agent.add_argument("--reports-dir", default=None)

    rescue = subparsers.add_parser("rescue", help="Plan de depannage lecture seule.")
    rescue.add_argument("--reports-dir", default=None)
    rescue.add_argument("--from-report", default=None, help="Chemin rapport existant.")

    subparsers.add_parser("menu", help="Menu principal simplifie.")

    web = subparsers.add_parser("web", help="Interface web securisee (localhost).")
    web.add_argument("--host", default=None)
    web.add_argument("--port", type=int, default=None)

    reboot_mon = subparsers.add_parser(
        "reboot-monitor",
        help="Surveillance reboot 24h et rapport detaille.",
    )
    reboot_mon.add_argument(
        "--analyze",
        action="store_true",
        help="Analyse instantanee des 24 dernieres heures.",
    )
    reboot_mon.add_argument("--hours", type=float, default=None)
    reboot_mon.add_argument("--interval", type=int, default=None)
    reboot_mon.add_argument("--reports-dir", default=None)

    plugins = subparsers.add_parser("plugins", help="Catalogue et installation de plugins.")
    plugins_sub = plugins.add_subparsers(dest="plugins_command")
    plugins_sub.add_parser("list", help="Lister le catalogue.")
    install_plugin = plugins_sub.add_parser("install", help="Installer un plugin.")
    install_plugin.add_argument("plugin_id")
    plugins_sub.add_parser("installed", help="Plugins installes localement.")

    return parser


def main(argv: list[str] | None = None) -> int:
    """Run the Obiora Doctor CLI."""

    config = load_config()
    parser = build_parser()
    args = parser.parse_args(argv)
    reports_dir = Path(getattr(args, "reports_dir", None) or config["reports_dir"])
    logger = setup_logging(
        str(config.get("logs_dir", "logs")),
        verbose=getattr(args, "verbose", False),
    )

    if args.command == "list-modules":
        for name in module_names():
            print(name)
        return 0

    if args.command == "menu":
        return run_launcher()

    modules = enabled_modules()
    engine = DiagnosticEngine(modules, config=config)

    if args.command == "scan" or args.command is None:
        logger.info("Demarrage scan diagnostic")
        report, errors = engine.run_validated(
            only_modules=getattr(args, "module", None),
            exclude_modules=getattr(args, "exclude_module", None),
        )
        if errors and getattr(args, "verbose", False):
            for error in errors:
                logger.warning("Schema: %s", error)

        if getattr(args, "json", False):
            print(render_json(report), end="")
            return 1 if report.score < 70 else 0

        output_dir = write_report_bundle(
            report,
            reports_dir,
            anonymize=getattr(args, "support", False),
        )
        if getattr(args, "zip", False):
            zip_path = export_report_zip(output_dir)
            if not getattr(args, "quiet", False):
                print(f"Archive: {zip_path}")

        if not getattr(args, "quiet", False):
            print_summary(report, str(output_dir), color=not getattr(args, "no_color", False))
        logger.info("Scan termine score=%s%%", report.score)
        return 1 if report.score < 70 else 0

    if args.command == "interactive":
        return run_interactive(engine, str(reports_dir), color=not args.no_color)

    if args.command == "watch":
        return run_watch(
            engine,
            interval=float(args.interval or config["watch_interval_seconds"]),
            modules=args.module,
            cache_dir=str(config["cache_dir"]),
            history_limit=int(config["watch_history_limit"]),
            color=not args.no_color,
        )

    if args.command == "compare":
        diff = compare_reports(load_report(Path(args.left)), load_report(Path(args.right)))
        print(render_compare_text(diff))
        return 1 if diff["score_delta"] < -10 else 0

    if args.command == "history":
        for entry in list_reports(reports_dir):
            print(f"{entry['date']}  {entry['score']}%  {entry['hostname']}  {entry['path']}")
        return 0

    if args.command == "clean":
        deleted = clean_old_reports(reports_dir, int(args.days or config["report_retention_days"]))
        print(f"{deleted} rapport(s) supprime(s).")
        return 0

    if args.command == "api":
        serve_api(args.host or config["api_host"], int(args.port or config["api_port"]), reports_dir)
        return 0

    if args.command == "bench":
        results = run_full_benchmark(str(config.get("cache_dir", "cache")))
        print(render_bench_text(results))
        return 0

    if args.command == "agent":
        return run_agent(
            engine,
            interval=args.interval,
            cache_dir=str(config.get("cache_dir", "cache")),
            reports_dir=str(reports_dir),
            once=args.once,
        )

    if args.command == "rescue":
        if args.from_report:
            data = json.loads(Path(args.from_report).read_text(encoding="utf-8"))
            from core.models import Finding, ModuleResult, Report, Severity

            results = []
            for item in data.get("results", []):
                findings = [
                    Finding(
                        Severity(f["level"]),
                        f["title"],
                        f["details"],
                        f.get("recommendation", ""),
                        f.get("commands", []),
                    )
                    for f in item.get("findings", [])
                ]
                results.append(
                    ModuleResult(
                        item["module"],
                        item["status"],
                        item["score"],
                        findings,
                        item.get("metrics", {}),
                        item.get("duration_ms", 0),
                    )
                )
            report = Report(
                data.get("version", VERSION),
                data.get("generated_at", ""),
                data.get("host", {}),
                data.get("score", 0),
                results,
            )
        else:
            report = engine.run()
        print(generate_rescue_plan(report))
        return 1 if report.score < 70 else 0

    if args.command == "web":
        serve_web(
            args.host or config.get("web_host", "127.0.0.1"),
            int(args.port or config.get("web_port", 8766)),
            reports_dir,
            engine,
        )
        return 0

    if args.command == "reboot-monitor":
        if getattr(args, "analyze", False):
            analysis = analyze_last_24h(engine.runner)
            print(render_reboot_report_text(analysis))
            return 0
        return run_reboot_watch(
            engine,
            hours=float(args.hours or config.get("reboot_monitor_hours", 24)),
            interval_minutes=int(
                args.interval or config.get("reboot_monitor_interval_minutes", 5)
            ),
            cache_dir=str(config.get("cache_dir", "cache")),
            reports_dir=str(reports_dir),
        )

    if args.command == "plugins":
        from core.plugin_store import install_plugin_from_catalog, list_installed_plugins, load_catalog

        if args.plugins_command == "list":
            for entry in load_catalog():
                print(f"{entry.get('id')}: {entry.get('name')} ({entry.get('version')})")
            return 0
        if args.plugins_command == "installed":
            for name in list_installed_plugins():
                print(name)
            return 0
        if args.plugins_command == "install":
            path = install_plugin_from_catalog(args.plugin_id)
            print(f"Plugin installe: {path}")
            return 0
        parser.parse_args(["plugins", "--help"])
        return 2

    parser.print_help()
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
