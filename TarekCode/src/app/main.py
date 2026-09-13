"""
Entry point for OpenCode API Manager.

- Single instance via QLockFile / Windows mutex
- No console window in bundled EXE (--windowed)
- Immediate UI, background refresh
- DPAPI / Credential Manager isolation

Usage:
  python -m app.main
  or via PyInstaller EXE
"""
from __future__ import annotations

import logging
import os
import sys
from pathlib import Path

# Ensure src is on path when running as script
if __package__ in (None, ""):
    # When executed via `python src/app/main.py` directly
    sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from PySide6.QtCore import QLockFile, QDir, QStandardPaths
from PySide6.QtWidgets import QApplication, QMessageBox
from PySide6.QtGui import QIcon

from app.utils.redaction import redact_text


# --- Centralized Redaction Filter ---
class RedactionFilter(logging.Filter):
    """Ensures all log records pass through redaction before output."""
    def filter(self, record: logging.LogRecord) -> bool:
        if isinstance(record.msg, str):
            record.msg = redact_text(record.msg)
        # Also redact args if they contain strings
        if record.args:
            new_args = []
            for arg in record.args:
                if isinstance(arg, str):
                    new_args.append(redact_text(arg))
                elif isinstance(arg, dict):
                    new_args.append(redact_text(str(arg)))
                else:
                    new_args.append(arg)
            record.args = tuple(new_args)
        return True


def setup_logging(level: int = logging.INFO, log_file: str | None = None) -> None:
    """Configure application-wide logging with redaction filter."""
    handlers: list[logging.Handler] = [logging.StreamHandler()]
    if log_file:
        handlers.append(logging.FileHandler(log_file, encoding="utf-8"))

    for h in handlers:
        h.addFilter(RedactionFilter())
        h.setFormatter(logging.Formatter(
            "%(asctime)s | %(levelname)s | %(name)s | %(message)s"
        ))

    logging.basicConfig(level=level, handlers=handlers, force=True)

    # Silence noisy loggers
    logging.getLogger("PySide6").setLevel(logging.WARNING)
    logging.getLogger("keyring").setLevel(logging.WARNING)


setup_logging()
logger = logging.getLogger(__name__)


def ensure_single_instance(app: QApplication) -> QLockFile | None:
    """
    Single instance guard per spec §28.
    Uses QLockFile at %TEMP%/OpenCodeAPIManager.lock
    Returns lock (kept alive) or None if already running.
    """
    lock_path = Path(os.environ.get("TEMP", str(Path.home()))) / "OpenCodeAPIManager.lock"
    lock = QLockFile(str(lock_path))
    lock.setStaleLockTime(0)
    # Try to acquire lock with 100ms timeout
    if not lock.tryLock(100):
        return None
    # Write PID to lock file for debugging
    try:
        lock.write(os.getpid().to_bytes(8, "little", signed=False))
    except Exception:
        pass
    return lock


def _try_focus_existing_window() -> bool:
    """Try to find and focus existing OpenCode API Manager window (Windows only)."""
    if os.name != "nt":
        return False
    try:
        import ctypes
        user32 = ctypes.windll.user32
        # Find window by class name or title
        hwnd = user32.FindWindowW(None, "OpenCode API Manager")
        if hwnd:
            # Restore if minimized
            user32.ShowWindow(hwnd, 9)  # SW_RESTORE
            user32.SetForegroundWindow(hwnd)
            return True
    except Exception:
        pass
    return False


def main() -> int:
    app = QApplication(sys.argv)
    app.setApplicationName("OpenCode API Manager")
    app.setOrganizationName("OpenCodeAPI")
    app.setApplicationVersion("1.0.0")

    # Single instance check
    lock = ensure_single_instance(app)
    if lock is None:
        # Try to focus existing instance
        _try_focus_existing_window()
        # Use QMessageBox if possible, else just exit
        try:
            QMessageBox.warning(
                None,
                "OpenCode API Manager",
                "Another instance is already running.\n\nOnly one instance can perform Switch at a time.",
            )
        except Exception:
            pass
        logger.warning("Second instance blocked by mutex")
        return 0

    # Style
    app.setStyle("Fusion")

    # Lazy imports after QApplication init
    from app.infrastructure.metadata_store import MetadataStore
    from app.infrastructure.secure_store import CredentialStore
    from app.opencode.adapter import OpenCodeAdapter
    from app.selection.engine import SelectionEngine
    from app.services.application_service import ApplicationService
    from app.ui.main_window import MainWindow

    metadata = MetadataStore()
    creds = CredentialStore()
    adapter = OpenCodeAdapter()
    sel = SelectionEngine()
    service = ApplicationService(
        metadata_store=metadata,
        credential_store=creds,
        opencode_adapter=adapter,
        selection_engine=sel,
    )

    # Log diagnostics at startup (non-blocking)
    try:
        diag = service.get_diagnostics()
        logger.info(f"Startup diagnostics: {diag}")
    except Exception as e:
        logger.warning(f"Diagnostics failed: {e}")

    win = MainWindow(app_service=service)
    # Keep lock alive as attribute
    win._single_instance_lock = lock  # type: ignore
    win.show()
    return app.exec()


if __name__ == "__main__":
    sys.exit(main())
