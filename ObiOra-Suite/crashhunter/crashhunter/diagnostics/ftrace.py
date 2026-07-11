"""ftrace recorder — fail-safe, filtered, watchdog-protected."""

from __future__ import annotations

import json
import logging
import os
import threading
import time
import uuid
from contextlib import contextmanager
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Iterator

try:
    import fcntl
except ImportError:  # pragma: no cover - Windows dev environments
    fcntl = None  # type: ignore[assignment]

from crashhunter.config.settings import FtraceSettings

logger = logging.getLogger("crashhunter.ftrace")

TRACING_PATHS = (
    Path("/sys/kernel/tracing"),
    Path("/sys/kernel/debug/tracing"),
)

# Candidate graph functions — matched against available_filter_functions at runtime.
FUNCTION_GRAPH_CANDIDATES: tuple[str, ...] = (
    "schedule",
    "schedule_timeout",
    "__schedule",
    "schedule_preempt",
    "rcu_",
    "mutex_",
    "down_",
    "up_",
    "blk_mq_",
    "submit_bio",
    "futex_",
    "irq_enter",
    "irq_exit",
    "softirq",
    "kvm_",
    "vcpu_",
    "queue_work",
    "process_one_work",
    "call_rwsem_down_read_failed",
    "rwsem_",
)

TRACER_MAX_SECONDS: dict[str, str] = {
    "function_graph": "function_graph_max_seconds",
    "irqsoff": "irqsoff_max_seconds",
    "preemptoff": "preemptoff_max_seconds",
    "wakeup": "wakeup_max_seconds",
}


@dataclass
class _SavedTracefsState:
    tracer: str = "nop"
    tracing_on: str = "0"
    graph_functions: str = ""
    ftrace_filter: str = ""
    buffer_size_kb: str = ""


@dataclass
class _ActiveCapture:
    capture_id: str
    owner_pid: int
    tracer: str
    tracefs_root: str
    started_at: float
    graph_functions: list[str] = field(default_factory=list)


class FtraceRecorder:
    """Enable ftrace tracers during incident mode with strict safety guarantees."""

    TRACERS = ("function_graph", "irqsoff", "preemptoff", "wakeup")
    _thread_lock = threading.Lock()

    def __init__(
        self,
        output_dir: Path,
        settings: FtraceSettings,
        state_dir: Path,
        shutdown_event: threading.Event | None = None,
    ) -> None:
        self.output_dir = output_dir
        self.settings = settings
        self.state_dir = state_dir
        self.shutdown_event = shutdown_event or threading.Event()
        self.state_dir.mkdir(parents=True, exist_ok=True)
        self.lock_file = self.state_dir / "ftrace.lock"
        self.session_file = self.state_dir / "ftrace_session.json"
        self._lock_fd: int | None = None

    @property
    def tracing_root(self) -> Path | None:
        for root in TRACING_PATHS:
            if (root / "current_tracer").exists():
                return root
        return None

    def is_available(self) -> bool:
        return self.tracing_root is not None

    def recover_abandoned_sessions(self) -> dict[str, Any] | None:
        """On startup: cleanup only sessions owned by CrashHunter."""
        root = self.tracing_root
        if root is None:
            return None

        state = self._read_session_state()
        tracing_on = self._read_tracefs(root, "tracing_on")
        current = self._read_tracefs(root, "current_tracer")

        if not state:
            return {"recovered": False, "reason": "no_crashhunter_state"}

        owner_pid = int(state.get("owner_pid", 0))
        capture_id = str(state.get("capture_id", ""))
        tracer = str(state.get("tracer", ""))
        started_at = float(state.get("started_at", 0))
        age = time.time() - started_at if started_at else 0.0
        pid_alive = owner_pid > 0 and self._pid_alive(owner_pid)

        if tracing_on != "1" and current in ("", "nop"):
            self._clear_session_state()
            return {"recovered": False, "reason": "tracefs_already_idle"}

        if pid_alive and age < self._max_duration_for(tracer) + 2:
            return {"recovered": False, "reason": "active_capture_still_valid", "capture_id": capture_id}

        logger.critical(
            "FTRACE_ABANDONED_SESSION_RECOVERED capture_id=%s owner_pid=%s tracer=%s age=%.2fs",
            capture_id,
            owner_pid,
            tracer or current,
            age,
        )
        self._cleanup_tracefs(root, state.get("graph_functions") or [])
        self._clear_session_state()
        return {
            "recovered": True,
            "capture_id": capture_id,
            "owner_pid": owner_pid,
            "tracer": tracer or current,
            "age_seconds": age,
        }

    def record_all(self) -> dict[str, Any]:
        if not self.settings.enabled:
            return {"recorded": False, "reason": "disabled"}
        results: dict[str, Any] = {}
        for tracer in self.TRACERS:
            if self.shutdown_event.is_set():
                results[tracer] = {"recorded": False, "reason": "shutdown_requested"}
                break
            results[tracer] = self.record(tracer)
            if results[tracer].get("reason") == "ftrace_capture_already_active":
                break
        return {"tracers": results}

    def record(self, tracer: str = "function_graph") -> dict[str, Any]:
        if not self.settings.enabled:
            return {"available": True, "captured": False, "recorded": False, "reason": "disabled"}
        root = self.tracing_root
        if root is None:
            return {"available": False, "captured": False, "recorded": False, "reason": "ftrace_not_available"}
        if tracer not in self.TRACERS:
            tracer = "function_graph"

        if self.shutdown_event.is_set():
            return {
                "available": True,
                "captured": False,
                "recorded": False,
                "reason": "shutdown_requested",
            }

        if tracer == "function_graph":
            graph_functions = self._resolve_graph_functions(root)
            if not graph_functions:
                logger.warning("FTRACE_CAPTURE_SKIPPED_NO_SAFE_FILTER tracer=function_graph")
                return {
                    "available": True,
                    "captured": False,
                    "recorded": False,
                    "reason": "no_safe_filter_functions",
                }
        else:
            graph_functions = []

        self.output_dir.mkdir(parents=True, exist_ok=True)
        trace_file = self.output_dir / f"ftrace_{tracer}.txt"
        capture_id = uuid.uuid4().hex[:12]
        max_duration = self._max_duration_for(tracer)

        try:
            with self._capture_session(root, tracer, graph_functions, capture_id, max_duration):
                if self.shutdown_event.is_set():
                    return {
                        "available": True,
                        "captured": False,
                        "recorded": False,
                        "reason": "shutdown_requested",
                    }
                self._sleep_bounded(max_duration)
                trace_content = self._read_trace_limited(root, self.settings.trace_read_max_bytes)
                trace_file.write_text(trace_content, encoding="utf-8")
        except FtraceLockBusy:
            logger.warning("FTRACE_CAPTURE_LOCKED tracer=%s", tracer)
            return {
                "available": True,
                "captured": False,
                "recorded": False,
                "reason": "ftrace_capture_already_active",
            }
        except Exception as exc:
            logger.exception("FTRACE_CAPTURE_FAILED tracer=%s error=%s", tracer, exc)
            return {"available": True, "captured": False, "recorded": False, "reason": str(exc)}

        lines = len(trace_content.splitlines()) if trace_content else 0
        logger.info(
            "FTRACE_CAPTURE_STOP capture_id=%s tracer=%s lines=%d duration_max=%.2fs",
            capture_id,
            tracer,
            lines,
            max_duration,
        )
        return {
            "available": True,
            "captured": True,
            "recorded": True,
            "tracer": tracer,
            "trace_file": str(trace_file),
            "lines": lines,
            "duration_seconds": max_duration,
            "graph_functions": graph_functions,
            "capture_id": capture_id,
        }

    @contextmanager
    def _capture_session(
        self,
        root: Path,
        tracer: str,
        graph_functions: list[str],
        capture_id: str,
        max_duration: float,
    ) -> Iterator[None]:
        saved = _SavedTracefsState()
        watchdog: threading.Timer | None = None
        lock_acquired = False
        try:
            self._acquire_lock()
            lock_acquired = True
            saved = self._save_tracefs_state(root)
            self._write_session_state(
                _ActiveCapture(
                    capture_id=capture_id,
                    owner_pid=os.getpid(),
                    tracer=tracer,
                    tracefs_root=str(root),
                    started_at=time.time(),
                    graph_functions=graph_functions,
                )
            )
            self._prepare_tracefs(root, tracer, graph_functions)
            self._enable_tracefs(root, tracer)
            logger.info(
                "FTRACE_CAPTURE_START capture_id=%s tracer=%s root=%s filters=%d max=%.2fs",
                capture_id,
                tracer,
                root,
                len(graph_functions),
                max_duration,
            )
            if self.settings.watchdog_enabled:
                watchdog = threading.Timer(
                    max_duration + 0.25,
                    self._watchdog_force_cleanup,
                    args=(root, graph_functions, capture_id, tracer),
                )
                watchdog.daemon = True
                watchdog.start()
            yield
        finally:
            if watchdog is not None:
                watchdog.cancel()
            try:
                self._cleanup_tracefs(root, graph_functions, saved)
            finally:
                self._clear_session_state()
                if lock_acquired:
                    self._release_lock()

    def _watchdog_force_cleanup(
        self,
        root: Path,
        graph_functions: list[str],
        capture_id: str,
        tracer: str,
    ) -> None:
        logger.critical(
            "FTRACE_WATCHDOG_TIMEOUT capture_id=%s tracer=%s — forcing tracefs cleanup",
            capture_id,
            tracer,
        )
        self._cleanup_tracefs(root, graph_functions)

    def _cleanup_tracefs(
        self,
        root: Path,
        graph_functions: list[str],
        saved: _SavedTracefsState | None = None,
    ) -> None:
        try:
            self._write_tracefs(root, "tracing_on", "0")
        except Exception:
            logger.exception("ftrace cleanup: failed to disable tracing_on")

        try:
            self._write_tracefs(root, "current_tracer", "nop")
        except Exception:
            logger.exception("ftrace cleanup: failed to reset current_tracer")

        try:
            if graph_functions:
                self._write_tracefs(root, "set_graph_function", "")
        except Exception:
            logger.exception("ftrace cleanup: failed to clear set_graph_function")

        try:
            self._write_tracefs(root, "set_ftrace_filter", "")
        except Exception:
            logger.exception("ftrace cleanup: failed to clear set_ftrace_filter")

        if saved is not None:
            try:
                if saved.buffer_size_kb:
                    self._write_tracefs(root, "buffer_size_kb", saved.buffer_size_kb)
            except Exception:
                logger.exception("ftrace cleanup: failed to restore buffer_size_kb")

    def _prepare_tracefs(self, root: Path, tracer: str, graph_functions: list[str]) -> None:
        if self.settings.buffer_size_kb > 0:
            self._write_tracefs(root, "buffer_size_kb", str(self.settings.buffer_size_kb))
        self._write_tracefs(root, "tracing_on", "0")
        self._write_tracefs(root, "trace", "")
        if tracer == "function_graph" and graph_functions:
            self._write_tracefs(root, "set_graph_function", "\n".join(graph_functions))
        self._write_tracefs(root, "set_ftrace_filter", "")

    def _enable_tracefs(self, root: Path, tracer: str) -> None:
        self._write_tracefs(root, "current_tracer", tracer)
        self._write_tracefs(root, "tracing_on", "1")

    def _resolve_graph_functions(self, root: Path) -> list[str]:
        available_path = root / "available_filter_functions"
        if not available_path.exists():
            return []
        try:
            available = available_path.read_text(encoding="utf-8", errors="replace").splitlines()
        except OSError:
            return []

        available_set = {line.strip() for line in available if line.strip()}
        selected: list[str] = []
        for candidate in FUNCTION_GRAPH_CANDIDATES:
            if candidate.endswith("_"):
                matches = sorted(name for name in available_set if name.startswith(candidate))
                selected.extend(matches[:3])
            elif candidate in available_set:
                selected.append(candidate)

        # Deduplicate while preserving order; cap to avoid oversized filters.
        seen: set[str] = set()
        unique: list[str] = []
        for name in selected:
            if name not in seen:
                seen.add(name)
                unique.append(name)
            if len(unique) >= self.settings.max_graph_functions:
                break
        return unique

    def _save_tracefs_state(self, root: Path) -> _SavedTracefsState:
        return _SavedTracefsState(
            tracer=self._read_tracefs(root, "current_tracer") or "nop",
            tracing_on=self._read_tracefs(root, "tracing_on") or "0",
            graph_functions=self._read_tracefs(root, "set_graph_function"),
            ftrace_filter=self._read_tracefs(root, "set_ftrace_filter"),
            buffer_size_kb=self._read_tracefs(root, "buffer_size_kb"),
        )

    def _read_trace_limited(self, root: Path, max_bytes: int) -> str:
        trace_path = root / "trace"
        try:
            with trace_path.open("r", encoding="utf-8", errors="replace") as fh:
                return fh.read(max_bytes)
        except OSError as exc:
            logger.warning("ftrace trace read failed: %s", exc)
            return ""

    def _sleep_bounded(self, duration: float) -> None:
        deadline = time.monotonic() + duration
        while time.monotonic() < deadline:
            if self.shutdown_event.is_set():
                break
            time.sleep(min(0.1, deadline - time.monotonic()))

    def _max_duration_for(self, tracer: str) -> float:
        attr = TRACER_MAX_SECONDS.get(tracer)
        if attr:
            return float(getattr(self.settings, attr))
        return float(self.settings.duration_seconds)

    def _acquire_lock(self) -> None:
        if fcntl is None:
            acquired = self._thread_lock.acquire(timeout=self.settings.lock_timeout_seconds)
            if not acquired:
                raise FtraceLockBusy()
            return

        self.lock_file.parent.mkdir(parents=True, exist_ok=True)
        fd = os.open(str(self.lock_file), os.O_CREAT | os.O_RDWR, 0o600)
        deadline = time.monotonic() + self.settings.lock_timeout_seconds
        while True:
            try:
                fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
                self._lock_fd = fd
                return
            except BlockingIOError:
                if time.monotonic() >= deadline:
                    os.close(fd)
                    raise FtraceLockBusy()
                time.sleep(0.05)

    def _release_lock(self) -> None:
        if fcntl is None:
            if self._thread_lock.locked():
                self._thread_lock.release()
            return

        if self._lock_fd is None:
            return
        try:
            fcntl.flock(self._lock_fd, fcntl.LOCK_UN)
        finally:
            os.close(self._lock_fd)
            self._lock_fd = None

    def _write_session_state(self, capture: _ActiveCapture) -> None:
        payload = {
            "capture_id": capture.capture_id,
            "owner_pid": capture.owner_pid,
            "tracer": capture.tracer,
            "tracefs_root": capture.tracefs_root,
            "started_at": capture.started_at,
            "graph_functions": capture.graph_functions,
        }
        tmp = self.session_file.with_suffix(".tmp")
        tmp.write_text(json.dumps(payload), encoding="utf-8")
        tmp.replace(self.session_file)

    def _read_session_state(self) -> dict[str, Any] | None:
        if not self.session_file.exists():
            return None
        try:
            data = json.loads(self.session_file.read_text(encoding="utf-8"))
            return data if isinstance(data, dict) else None
        except (OSError, json.JSONDecodeError):
            return None

    def _clear_session_state(self) -> None:
        try:
            self.session_file.unlink(missing_ok=True)
        except OSError:
            logger.exception("Failed to clear ftrace session state")

    @staticmethod
    def _read_tracefs(root: Path, name: str) -> str:
        try:
            return (root / name).read_text(encoding="utf-8", errors="replace").strip()
        except OSError:
            return ""

    @staticmethod
    def _write_tracefs(root: Path, name: str, value: str) -> None:
        (root / name).write_text(value, encoding="ascii")

    @staticmethod
    def _pid_alive(pid: int) -> bool:
        if pid <= 0:
            return False
        try:
            os.kill(pid, 0)
            return True
        except OSError:
            return False


class FtraceLockBusy(Exception):
    """Exclusive ftrace lock already held."""
