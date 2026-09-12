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

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(name)s | %(message)s",
)

logger = logging.getLogger(__name__)


def ensure_single_instance(app: QApplication) -> QLockFile | None:
    """
    Single instance guard per spec §28.
    Uses QLockFile at %TEMP%/OpenCodeAPIManager.lock
    Returns lock (kept alive) or None if already running.
    """
    lock_path = Path(os.environ.get("TEMP", str(Path.home()))) / "OpenCodeAPIManager.lock"
    # Alternative: QStandardPaths::TempLocation
    lock = QLockFile(str(lock_path))
    lock.setStaleLockTime(0)
    if not lock.tryLock(100):
        # Try to show message then exit
        return None
    return lock


def main() -> int:
    app = QApplication(sys.argv)
    app.setApplicationName("OpenCode API Manager")
    app.setOrganizationName("OpenCodeAPI")
    app.setApplicationVersion("1.0.0")

    # Single instance check
    lock = ensure_single_instance(app)
    if lock is None:
        # Use QMessageBox if possible, else just exit
        try:
            QMessageBox.warning(
                None,
                "OpenCode API Manager",
                "Another instance is already running.\n\nOnly one instance can perform Switch at a time.",
            )
        except Exception:
            pass
        # Try to focus existing? On Windows we could FindWindow, but simple exit is spec-compliant ("prevent second instance")
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
