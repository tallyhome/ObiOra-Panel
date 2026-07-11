"""Crash Hunter CLI."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from crashhunter import __version__
from crashhunter.config.settings import load_settings
from crashhunter.daemon import CrashHunterDaemon
from crashhunter.report.blackbox import BlackBoxRecorder
from crashhunter.report.generator import ReportGenerator
from crashhunter.simulation.replay import SimulationReplayer
from crashhunter.storage.incident_store import IncidentStore
from crashhunter.storage.ring_buffer import RingBuffer
from crashhunter.storage.state_store import StateStore
from crashhunter.utils.logging_setup import setup_logging


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="crashhunter",
        description="Crash Hunter — Linux freeze diagnosis daemon",
    )
    parser.add_argument("--config", type=Path, help="Path to config.yaml")
    parser.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    sub = parser.add_subparsers(dest="command")

    sub.add_parser("run", help="Start the sampling daemon")

    status_parser = sub.add_parser("status", help="Show daemon state")
    status_parser.add_argument("--json", action="store_true", help="JSON output")

    report_parser = sub.add_parser("report", help="Generate report from ring buffer")
    report_parser.add_argument("--force", action="store_true", help="Generate even without reboot")

    sample_parser = sub.add_parser("sample", help="Collect one snapshot and print JSON")
    sample_parser.add_argument("-o", "--output", type=Path, help="Write to file")

    sub.add_parser("check-reboot", help="Check if reboot was detected")

    incident_parser = sub.add_parser("incidents", help="List incident folders")
    incident_parser.add_argument("--json", action="store_true")

    sim_parser = sub.add_parser("simulate", help="Replay crash/incident folder and regenerate report")
    sim_parser.add_argument("folder", type=Path, help="Incident or report folder path")
    sim_parser.add_argument("--step-by-step", action="store_true", help="Replay timeline second by second")

    bundle_parser = sub.add_parser("bundle", help="Create diagnostic bundle from latest report")
    bundle_parser.add_argument("--report-dir", type=Path, help="Specific report directory")

    sub.add_parser("witness-server", help="Start Remote Witness receiver (VPS)")
    witness_status = sub.add_parser("witness-status", help="Show witness host status")
    witness_status.add_argument("--json", action="store_true")

    sub.add_parser("web", help="Start CrashHunter Web Dashboard")

    ovh_parser = sub.add_parser("ovh-report", help="Generate OVH support ticket package")
    ovh_parser.add_argument("--report-dir", type=Path, help="Specific report directory")

    nc_parser = sub.add_parser("netconsole", help="Configure netconsole to VPS")
    nc_parser.add_argument("--remove", action="store_true", help="Remove netconsole config")

    return parser


def _settings(args: argparse.Namespace):
    return load_settings(config_path=getattr(args, "config", None))


def cmd_run(args: argparse.Namespace) -> int:
    return CrashHunterDaemon(_settings(args)).run()


def cmd_status(args: argparse.Namespace) -> int:
    settings = _settings(args)
    state = StateStore(settings.boot_id_file, settings.last_uptime_file, settings.last_clock_file)
    ring = RingBuffer(settings.ring_capacity, settings.ring_dir)
    incidents = IncidentStore(settings.incident_dir).list_incidents()
    info = {
        "version": __version__,
        "boot_id": state.current_boot_id(),
        "uptime": state.current_uptime(),
        "ring_count": ring.count,
        "ring_capacity": settings.ring_capacity,
        "reports_dir": str(settings.reports_dir),
        "incidents": incidents,
        "silent_freeze_detection": settings.incident.enabled,
        "config": str(settings.config_path) if settings.config_path else "default",
    }
    if args.json:
        print(json.dumps(info, indent=2))
    else:
        print(f"Crash Hunter v{__version__}")
        print(f"Boot ID:    {info['boot_id']}")
        print(f"Uptime:     {info['uptime']:.1f}s")
        print(f"Ring:       {info['ring_count']}/{info['ring_capacity']} snapshots")
        print(f"Incidents:  {len(incidents)}")
        print(f"Freeze det: {'enabled' if settings.incident.enabled else 'disabled'}")
        print(f"Reports:    {info['reports_dir']}")
    return 0


def cmd_report(args: argparse.Namespace) -> int:
    settings = _settings(args)
    settings.ensure_directories()
    setup_logging(settings.log_level, settings.logs_dir / "cli.log")
    state = StateStore(settings.boot_id_file, settings.last_uptime_file, settings.last_clock_file)
    reboot_info = state.detect_reboot()
    if not args.force and not reboot_info.get("reboot_detected"):
        print("No reboot detected. Use --force to generate anyway.")
        return 1
    ring = RingBuffer(settings.ring_capacity, settings.ring_dir)
    ring.load_from_disk()
    blackbox = BlackBoxRecorder(ring, settings.blackbox_memory_file)
    report = ReportGenerator(settings).generate(blackbox, reboot_info)
    print(f"Report generated: {report['report_id']}")
    return 0


def cmd_sample(args: argparse.Namespace) -> int:
    from crashhunter.samplers.aggregator import SnapshotAggregator

    settings = _settings(args)
    snapshot = SnapshotAggregator(settings).collect()
    text = json.dumps(snapshot, indent=2, ensure_ascii=False)
    if args.output:
        args.output.write_text(text, encoding="utf-8")
        print(f"Snapshot written to {args.output}")
    else:
        print(text)
    return 0


def cmd_check_reboot(args: argparse.Namespace) -> int:
    settings = _settings(args)
    state = StateStore(settings.boot_id_file, settings.last_uptime_file, settings.last_clock_file)
    info = state.detect_reboot()
    print(json.dumps(info, indent=2))
    return 0 if not info.get("reboot_detected") else 2


def cmd_incidents(args: argparse.Namespace) -> int:
    settings = _settings(args)
    store = IncidentStore(settings.incident_dir)
    incidents = store.list_incidents()
    if args.json:
        print(json.dumps(incidents, indent=2))
    else:
        for inc in incidents:
            count = store.count(inc)
            print(f"{inc}  ({count} emergency snapshots)")
    return 0


def cmd_simulate(args: argparse.Namespace) -> int:
    settings = _settings(args)
    settings.ensure_directories()
    setup_logging(settings.log_level, settings.logs_dir / "cli.log")
    replayer = SimulationReplayer(settings)
    folder = args.folder
    if not folder.exists():
        print(f"Folder not found: {folder}")
        return 1
    if getattr(args, "step_by_step", False):
        steps = replayer.replay_timeline_step_by_step(folder)
        for step in steps:
            print(step)
        print(f"\n{len(steps)} timeline steps replayed.")
        return 0
    if list(folder.glob("CrashReport_*.json")):
        report = replayer.replay_report_folder(folder)
    else:
        report = replayer.replay_incident_folder(folder)
    print(f"Simulation report generated: {report['report_id']}")
    if report.get("bundle_path"):
        print(f"Bundle: {report['bundle_path']}")
    return 0


def cmd_bundle(args: argparse.Namespace) -> int:
    settings = _settings(args)
    settings.ensure_directories()
    report_dir = args.report_dir
    if not report_dir:
        reports = sorted(settings.reports_dir.glob("CrashReport_*"), key=lambda p: p.stat().st_mtime, reverse=True)
        if not reports:
            print("No reports found.")
            return 1
        report_dir = reports[0]
    json_files = list(report_dir.glob("CrashReport_*.json"))
    if not json_files:
        print(f"No JSON report in {report_dir}")
        return 1
    report = json.loads(json_files[0].read_text(encoding="utf-8"))
    from crashhunter.report.exporters.bundle_export import export_bundle
    path = export_bundle(report, report_dir, settings.base_dir)
    print(f"Bundle created: {path}")
    return 0


def cmd_witness_server(args: argparse.Namespace) -> int:
    from crashhunter.witness.receiver import WitnessReceiver

    settings = _settings(args)
    settings.ensure_directories()
    setup_logging(settings.log_level, settings.logs_dir / "witness.log")
    return WitnessReceiver(settings).run()


def cmd_witness_status(args: argparse.Namespace) -> int:
    from crashhunter.witness.monitor import WitnessMonitor
    from crashhunter.witness.store import WitnessStore

    settings = _settings(args)
    store = WitnessStore(settings.witness_data_dir)
    monitor = WitnessMonitor(settings, store)
    status = monitor.check_all()
    if args.json:
        print(json.dumps(status, indent=2, default=str))
    else:
        for host in status:
            print(f"{host['host']}: {host['status']} (last {host['age_seconds']}s ago)")
    return 0


def cmd_web(args: argparse.Namespace) -> int:
    from crashhunter.web.server import WebDashboard

    settings = _settings(args)
    settings.ensure_directories()
    setup_logging(settings.log_level, settings.logs_dir / "web.log")
    return WebDashboard(settings).run()


def cmd_ovh_report(args: argparse.Namespace) -> int:
    settings = _settings(args)
    settings.ensure_directories()
    report_dir = args.report_dir
    if not report_dir:
        reports = sorted(settings.reports_dir.glob("CrashReport_*"), key=lambda p: p.stat().st_mtime, reverse=True)
        if not reports:
            print("No reports found.")
            return 1
        report_dir = reports[0]
    json_files = list(report_dir.glob("CrashReport_*.json"))
    if not json_files:
        print(f"No JSON report in {report_dir}")
        return 1
    report = json.loads(json_files[0].read_text(encoding="utf-8"))
    from crashhunter.report.exporters.ovh_export import generate_ovh_report

    result = generate_ovh_report(report, report_dir, settings.base_dir)
    print(f"OVH summary: {result['summary_path']}")
    print(f"OVH JSON:    {result['json_path']}")
    print(f"Bundle:      {result['bundle_path']}")
    return 0


def cmd_netconsole(args: argparse.Namespace) -> int:
    from crashhunter.kernel.netconsole import NetconsoleManager

    settings = _settings(args)
    mgr = NetconsoleManager(settings)
    if getattr(args, "remove", False):
        result = mgr.remove()
    else:
        result = mgr.configure()
    print(json.dumps(result, indent=2))
    return 0 if result.get("configured") or result.get("removed") else 1


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)

    if args.command == "run" or args.command is None:
        return cmd_run(args)
    if args.command == "status":
        return cmd_status(args)
    if args.command == "report":
        return cmd_report(args)
    if args.command == "sample":
        return cmd_sample(args)
    if args.command == "check-reboot":
        return cmd_check_reboot(args)
    if args.command == "incidents":
        return cmd_incidents(args)
    if args.command == "simulate":
        return cmd_simulate(args)
    if args.command == "bundle":
        return cmd_bundle(args)
    if args.command == "witness-server":
        return cmd_witness_server(args)
    if args.command == "witness-status":
        return cmd_witness_status(args)
    if args.command == "web":
        return cmd_web(args)
    if args.command == "ovh-report":
        return cmd_ovh_report(args)
    if args.command == "netconsole":
        return cmd_netconsole(args)

    parser.print_help()
    return 1


if __name__ == "__main__":
    sys.exit(main())
