"""
MainWindow — compact, professional utility UI per spec §15, §18.

Goals:
- Beautiful, formal, compact, not large dashboard or gamer UI.
- Instant appearance, background refresh.
- Never show secrets.
- Responsive SWITCH, no blocking UI thread.
- Single instance handled at app startup, but window also guards concurrency.

Structure:
  ┌──────────────────────────────────────┐
  │ OpenCode API Manager            ⚙    │
  │ ACTIVE                                │
  │ Personal 02                          │
  │ OpenCode Zen                         │
  │ Remaining capacity                   │
  │ ████████░░░░  82%                    │
  │           [ SWITCH ]                  │
  │ APIs                                  │
  │ ● Personal 01  24%  Ready            │
  │ ...                                   │
  │ + Add API                ⋮ More       │
  └──────────────────────────────────────┘
"""
from __future__ import annotations

import logging
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

from PySide6.QtCore import Qt, Signal, QObject, QThread, QTimer, QSize
from PySide6.QtGui import QFont, QAction, QIcon
from PySide6.QtWidgets import (
    QMainWindow, QWidget, QVBoxLayout, QHBoxLayout, QLabel, QPushButton,
    QListWidget, QListWidgetItem, QFrame, QProgressBar, QMessageBox, QMenu,
    QDialog, QSpacerItem, QSizePolicy, QApplication
)

from app.domain.models import CredentialMeta, AuthStatus
from app.services.application_service import ApplicationService
from app.utils.redaction import fingerprint

logger = logging.getLogger(__name__)


# --- Workers ---
class SwitchWorker(QObject):
    finished = Signal(object)  # SwitchResult
    progress = Signal(str)
    error = Signal(str)

    def __init__(self, app_service: ApplicationService):
        super().__init__()
        self.app_service = app_service

    def run(self):
        self.app_service.set_progress_callback(lambda m: self.progress.emit(m))
        try:
            result = self.app_service.switch()
            self.finished.emit(result)
        except Exception as e:
            logger.exception("SwitchWorker error")
            self.error.emit(str(e))


class RefreshWorker(QObject):
    finished = Signal()

    def __init__(self, app_service: ApplicationService):
        super().__init__()
        self.app_service = app_service

    def run(self):
        try:
            # Reload happens via service; we just emit finished so UI can refresh
            self.app_service.refresh_all_stale(background=False)
        except Exception:
            pass
        self.finished.emit()


# --- List item widget ---
class ApiRowWidget(QFrame):
    def __init__(self, meta: CredentialMeta, is_active: bool, on_remove, on_copy_fingerprint):
        super().__init__()
        self.meta = meta
        self.setObjectName("ApiRow")
        self.setStyleSheet("""
            #ApiRow {
                background: #FFFFFF;
                border: 1px solid #E6E6E6;
                border-radius: 6px;
            }
            #ApiRow:hover { border: 1px solid #D0D0D0; background: #FAFAFA; }
        """)
        self.setFixedHeight(54)
        layout = QHBoxLayout(self)
        layout.setContentsMargins(10, 6, 10, 6)
        layout.setSpacing(8)

        # Bullet color per status
        color_map = {
            AuthStatus.ACTIVE: "#1A7F37",
            AuthStatus.READY: "#0969DA",
            AuthStatus.UNKNOWN: "#8B949E",
            AuthStatus.STALE: "#9A6700",
            AuthStatus.RATE_LIMITED: "#CF222E",
            AuthStatus.COOLDOWN: "#CF222E",
            AuthStatus.INVALID: "#CF222E",
            AuthStatus.UNAUTHORIZED: "#CF222E",
            AuthStatus.FAILED: "#CF222E",
        }
        bullet_color = color_map.get(meta.auth_status, "#8B949E")
        if is_active:
            bullet_color = "#1A7F37"

        bullet = QLabel("●")
        bullet.setStyleSheet(f"color: {bullet_color}; font-size: 10px;")
        bullet.setFixedWidth(12)
        layout.addWidget(bullet)

        # Name + provider vertical
        text_col = QVBoxLayout()
        text_col.setSpacing(1)
        name_lbl = QLabel(meta.name)
        name_font = QFont("Segoe UI", 9)
        name_font.setBold(True)
        name_lbl.setFont(name_font)
        name_lbl.setStyleSheet("color: #1F2328; border: none;")
        prov_lbl = QLabel(meta.provider)
        prov_lbl.setStyleSheet("color: #656D76; font-size: 11px; border: none;")
        text_col.addWidget(name_lbl)
        text_col.addWidget(prov_lbl)
        layout.addLayout(text_col, 1)

        # Capacity
        cap = meta.capacity
        if cap.is_unknown():
            cap_text = "—"
            cap_style = "color: #8B949E; font-size: 12px;"
        else:
            cap_text = f"{cap.percent}%"
            cap_style = "color: #1F2328; font-size: 12px; font-weight: 600;"
        cap_lbl = QLabel(cap_text)
        cap_lbl.setStyleSheet(f"border: none; {cap_style}")
        cap_lbl.setFixedWidth(42)
        cap_lbl.setAlignment(Qt.AlignRight | Qt.AlignVCenter)
        layout.addWidget(cap_lbl)

        # Small capacity bar (for known)
        if not cap.is_unknown():
            bar = QFrame()
            bar.setFixedSize(40, 4)
            bar.setStyleSheet("background: #E6E6E6; border-radius: 2px; border: none;")
            # inner fill via stylesheet trick: use QProgressBar instead
            # We'll just show text; keep simple
            pass

        # Status pill
        status_text = "Active" if is_active else {
            AuthStatus.READY: "Ready",
            AuthStatus.ACTIVE: "Active",
            AuthStatus.UNKNOWN: "Unknown",
            AuthStatus.RATE_LIMITED: "Limited",
            AuthStatus.COOLDOWN: "Cooldown",
            AuthStatus.INVALID: "Invalid",
            AuthStatus.UNAUTHORIZED: "Unauthorized",
            AuthStatus.FAILED: "Failed",
            AuthStatus.STALE: "Stale",
            AuthStatus.CHECKING: "Checking",
        }.get(meta.auth_status, str(meta.auth_status.value).title())
        status_lbl = QLabel(status_text)
        # Pill styling
        pill_bg = "#DDFFF0" if is_active else "#F3F4F6" if status_text in ("Ready","Unknown") else "#FFE2E2" if status_text in ("Invalid","Unauthorized","Failed","Limited","Cooldown") else "#FFF8C5"
        pill_fg = "#0A6B3A" if is_active else "#656D76" if status_text in ("Ready","Unknown") else "#9A1A1A" if status_text in ("Invalid","Unauthorized","Failed","Limited","Cooldown") else "#7A5D00"
        status_lbl.setStyleSheet(f"background: {pill_bg}; color: {pill_fg}; border-radius: 8px; padding: 2px 8px; font-size: 11px; border: none;")
        status_lbl.setFixedHeight(18)
        layout.addWidget(status_lbl)

        # Remove button (⋮ menu)
        menu_btn = QPushButton("⋮")
        menu_btn.setFixedSize(22, 22)
        menu_btn.setCursor(Qt.PointingHandCursor)
        menu_btn.setStyleSheet("""
            QPushButton { background: transparent; border: none; color: #656D76; font-size: 14px; }
            QPushButton:hover { background: #EEE; border-radius: 4px; }
        """)
        # context menu
        def show_menu():
            m = QMenu(self)
            m.setStyleSheet("QMenu { font-family: 'Segoe UI'; font-size: 12px; }")
            act_remove = QAction(f"Remove {meta.name}", self)
            act_copy = QAction("Copy fingerprint", self)
            m.addAction(act_copy)
            m.addSeparator()
            m.addAction(act_remove)
            act = m.exec(menu_btn.mapToGlobal(menu_btn.rect().bottomLeft()))
            if act == act_remove:
                on_remove(meta)
            elif act == act_copy:
                on_copy_fingerprint(meta)
        menu_btn.clicked.connect(show_menu)
        layout.addWidget(menu_btn)


# --- Add API Dialog ---
from app.ui.dialogs import AddApiDialog, DiagnosticsDialog  # lazy import inside file? We'll implement inline to avoid circular

# Instead define AddApiDialog here properly:
from PySide6.QtWidgets import QLineEdit, QComboBox, QFormLayout, QDialogButtonBox

class AddApiDialogInline(QDialog):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setWindowTitle("Add API")
        self.setModal(True)
        self.setFixedSize(380, 260)
        self.setStyleSheet("""
            QDialog { background: #FFFFFF; }
            QLabel { color: #1F2328; font-family: 'Segoe UI'; font-size: 12px; }
            QLineEdit { padding: 8px 10px; border: 1px solid #D0D7DE; border-radius: 6px; background: #FFFFFF; font-family: 'Segoe UI'; font-size: 12px; }
            QLineEdit:focus { border: 1px solid #0969DA; }
            QComboBox { padding: 6px 10px; border: 1px solid #D0D7DE; border-radius: 6px; background: #FFFFFF; }
            QPushButton { font-family: 'Segoe UI'; font-size: 12px; }
        """)
        layout = QVBoxLayout(self)
        layout.setContentsMargins(20, 16, 20, 16)
        layout.setSpacing(12)

        title = QLabel("Add new API credential")
        tf = QFont("Segoe UI", 10)
        tf.setBold(True)
        title.setFont(tf)
        layout.addWidget(title)

        form = QFormLayout()
        form.setSpacing(10)
        form.setLabelAlignment(Qt.AlignLeft)

        self.name_edit = QLineEdit()
        self.name_edit.setPlaceholderText("Personal 01")
        form.addRow("Name", self.name_edit)

        self.provider_combo = QComboBox()
        self.provider_combo.addItems(["opencode", "opencode-go", "openai", "anthropic", "google", "custom"])
        self.provider_combo.setEditable(True)
        # default to opencode (Zen)
        self.provider_combo.setCurrentText("opencode")
        form.addRow("Provider", self.provider_combo)

        self.key_edit = QLineEdit()
        self.key_edit.setEchoMode(QLineEdit.Password)
        self.key_edit.setPlaceholderText("sk-••••••••••••••••••••")
        form.addRow("API Key", self.key_edit)

        layout.addLayout(form)

        hint = QLabel("Key is stored securely via Windows Credential Manager / DPAPI. Never shown again.")
        hint.setStyleSheet("color: #656D76; font-size: 11px;")
        hint.setWordWrap(True)
        layout.addWidget(hint)

        btns = QDialogButtonBox(QDialogButtonBox.Cancel | QDialogButtonBox.Ok)
        btns.button(QDialogButtonBox.Ok).setText("Add")
        btns.button(QDialogButtonBox.Ok).setStyleSheet("""
            QPushButton { background: #1F2328; color: #FFFFFF; padding: 8px 16px; border-radius: 6px; font-weight: 600; min-width: 70px; }
            QPushButton:hover { background: #2D333B; }
            QPushButton:disabled { background: #E6E6E6; color: #8B949E; }
        """)
        btns.button(QDialogButtonBox.Cancel).setStyleSheet("""
            QPushButton { background: #FFFFFF; color: #1F2328; padding: 8px 16px; border: 1px solid #D0D7DE; border-radius: 6px; }
            QPushButton:hover { background: #F6F8FA; }
        """)
        btns.accepted.connect(self.accept)
        btns.rejected.connect(self.reject)
        layout.addWidget(btns)

        self.name_edit.textChanged.connect(self._validate)
        self.key_edit.textChanged.connect(self._validate)
        self._validate()

    def _validate(self):
        ok = bool(self.name_edit.text().strip() and self.key_edit.text().strip())
        self.findChild(QDialogButtonBox).button(QDialogButtonBox.Ok).setEnabled(ok)

    def get_values(self):
        return self.name_edit.text().strip(), self.provider_combo.currentText().strip() or "opencode", self.key_edit.text().strip()


# --- Main Window ---
class MainWindow(QMainWindow):
    def __init__(self, app_service: Optional[ApplicationService] = None):
        super().__init__()
        self.app_service = app_service or ApplicationService()
        self._switch_thread: Optional[QThread] = None
        self._switch_worker: Optional[SwitchWorker] = None
        self._switching = False

        self.setWindowTitle("OpenCode API Manager")
        self.setFixedSize(420, 620)
        self.setStyleSheet("QMainWindow { background: #F6F8FA; }")

        # Central
        central = QWidget()
        central.setStyleSheet("background: #F6F8FA;")
        self.setCentralWidget(central)
        root = QVBoxLayout(central)
        root.setContentsMargins(14, 14, 14, 14)
        root.setSpacing(10)

        # Header
        header = QHBoxLayout()
        header.setContentsMargins(2, 2, 2, 2)
        title_col = QVBoxLayout()
        title_lbl = QLabel("OpenCode API Manager")
        title_lbl.setStyleSheet("color: #1F2328; font-family: 'Segoe UI'; font-size: 14px; font-weight: 700;")
        subtitle = QLabel("Deterministic credential manager")
        subtitle.setStyleSheet("color: #656D76; font-family: 'Segoe UI'; font-size: 11px;")
        title_col.addWidget(title_lbl)
        title_col.addWidget(subtitle)
        header.addLayout(title_col, 1)

        self.settings_btn = QPushButton("⚙")
        self.settings_btn.setFixedSize(32, 32)
        self.settings_btn.setCursor(Qt.PointingHandCursor)
        self.settings_btn.setStyleSheet("""
            QPushButton { background: #FFFFFF; border: 1px solid #D0D7DE; border-radius: 16px; font-size: 14px; color: #656D76; }
            QPushButton:hover { background: #F6F8FA; color: #1F2328; border: 1px solid #C0C8D0; }
        """)
        self.settings_btn.setToolTip("Settings & Diagnostics")
        header.addWidget(self.settings_btn)
        root.addLayout(header)

        # ACTIVE card
        self.active_card = QFrame()
        self.active_card.setObjectName("ActiveCard")
        self.active_card.setStyleSheet("""
            #ActiveCard { background: #FFFFFF; border: 1px solid #D0D7DE; border-radius: 10px; }
        """)
        ac_layout = QVBoxLayout(self.active_card)
        ac_layout.setContentsMargins(16, 14, 16, 14)
        ac_layout.setSpacing(8)

        active_header = QHBoxLayout()
        lbl_active = QLabel("ACTIVE")
        lbl_active.setStyleSheet("color: #656D76; font-family: 'Segoe UI'; font-size: 10px; letter-spacing: 1.1px; border: none;")
        active_header.addWidget(lbl_active)
        active_header.addStretch()
        self.active_dot = QLabel("●")
        self.active_dot.setStyleSheet("color: #1A7F37; font-size: 8px; border: none;")
        active_header.addWidget(self.active_dot)
        ac_layout.addLayout(active_header)

        self.active_name = QLabel("— No active API —")
        self.active_name.setStyleSheet("color: #1F2328; font-family: 'Segoe UI'; font-size: 15px; font-weight: 700; border: none;")
        ac_layout.addWidget(self.active_name)

        self.active_provider = QLabel("Select or add an API below")
        self.active_provider.setStyleSheet("color: #656D76; font-family: 'Segoe UI'; font-size: 12px; border: none;")
        ac_layout.addWidget(self.active_provider)

        # Remaining capacity section
        cap_label = QLabel("Remaining capacity")
        cap_label.setStyleSheet("color: #656D76; font-family: 'Segoe UI'; font-size: 11px; border: none; margin-top: 6px;")
        ac_layout.addWidget(cap_label)

        cap_row = QHBoxLayout()
        cap_row.setSpacing(8)
        self.capacity_bar = QProgressBar()
        self.capacity_bar.setFixedHeight(8)
        self.capacity_bar.setTextVisible(False)
        self.capacity_bar.setRange(0, 100)
        self.capacity_bar.setValue(0)
        self.capacity_bar.setVisible(False)  # Hidden by default (UNKNOWN)
        self.capacity_bar.setStyleSheet("""
            QProgressBar { background: #EAEEF2; border: none; border-radius: 4px; }
            QProgressBar::chunk { background: #1F2328; border-radius: 4px; }
        """)
        cap_row.addWidget(self.capacity_bar, 1)
        self.capacity_pct = QLabel("UNKNOWN")
        self.capacity_pct.setStyleSheet("color: #8B949E; font-family: 'Segoe UI'; font-size: 12px; font-weight: 600; border: none;")
        self.capacity_pct.setFixedWidth(70)
        self.capacity_pct.setAlignment(Qt.AlignRight | Qt.AlignVCenter)
        cap_row.addWidget(self.capacity_pct)
        ac_layout.addLayout(cap_row)

        # Switch button
        self.switch_btn = QPushButton("SWITCH")
        self.switch_btn.setFixedHeight(40)
        self.switch_btn.setCursor(Qt.PointingHandCursor)
        self.switch_btn.setStyleSheet("""
            QPushButton {
                background: #1F2328;
                color: #FFFFFF;
                border: none;
                border-radius: 8px;
                font-family: 'Segoe UI';
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 0.8px;
            }
            QPushButton:hover { background: #2D333B; }
            QPushButton:pressed { background: #0A0E12; }
            QPushButton:disabled { background: #E6E6E6; color: #8B949E; }
        """)
        ac_layout.addWidget(self.switch_btn)
        # Status under switch
        self.switch_status = QLabel("")
        self.switch_status.setStyleSheet("color: #656D76; font-family: 'Segoe UI'; font-size: 11px; border: none;")
        self.switch_status.setWordWrap(True)
        self.switch_status.setAlignment(Qt.AlignCenter)
        ac_layout.addWidget(self.switch_status)

        root.addWidget(self.active_card)

        # APIs header
        apis_header = QHBoxLayout()
        apis_lbl = QLabel("APIs")
        apis_lbl.setStyleSheet("color: #1F2328; font-family: 'Segoe UI'; font-size: 12px; font-weight: 600;")
        apis_header.addWidget(apis_lbl)
        apis_header.addStretch()
        self.count_lbl = QLabel("0")
        self.count_lbl.setStyleSheet("color: #656D76; font-family: 'Segoe UI'; font-size: 11px; background: #FFFFFF; border: 1px solid #D0D7DE; border-radius: 8px; padding: 1px 7px;")
        apis_header.addWidget(self.count_lbl)
        root.addLayout(apis_header)

        # List
        self.api_list = QListWidget()
        self.api_list.setStyleSheet("""
            QListWidget { background: transparent; border: none; }
            QListWidget::item { background: transparent; border: none; padding: 3px 0px; }
            QListWidget::item:selected { background: transparent; }
        """)
        self.api_list.setVerticalScrollMode(QListWidget.ScrollPerPixel)
        self.api_list.setSpacing(2)
        root.addWidget(self.api_list, 1)

        # Bottom bar
        bottom = QHBoxLayout()
        self.add_btn = QPushButton("+  Add API")
        self.add_btn.setCursor(Qt.PointingHandCursor)
        self.add_btn.setFixedHeight(34)
        self.add_btn.setStyleSheet("""
            QPushButton { background: #FFFFFF; color: #1F2328; border: 1px solid #D0D7DE; border-radius: 8px; font-family: 'Segoe UI'; font-size: 12px; font-weight: 600; padding: 0 14px; }
            QPushButton:hover { background: #F6F8FA; border: 1px solid #C0C8D0; }
        """)
        bottom.addWidget(self.add_btn)

        bottom.addStretch()

        self.more_btn = QPushButton("⋮ More")
        self.more_btn.setFixedHeight(34)
        self.more_btn.setCursor(Qt.PointingHandCursor)
        self.more_btn.setStyleSheet("""
            QPushButton { background: transparent; color: #656D76; border: 1px solid #D0D7DE; border-radius: 8px; font-family: 'Segoe UI'; font-size: 12px; padding: 0 12px; }
            QPushButton:hover { background: #FFFFFF; color: #1F2328; }
        """)
        bottom.addWidget(self.more_btn)
        root.addLayout(bottom)

        # Signals
        self.switch_btn.clicked.connect(self.on_switch)
        self.add_btn.clicked.connect(self.on_add_api)
        self.settings_btn.clicked.connect(self.on_diagnostics)
        self.more_btn.clicked.connect(self.on_more)

        # Initial refresh (instant UI, then background)
        QTimer.singleShot(50, self.refresh_ui)
        QTimer.singleShot(300, self.background_refresh)

        # Periodic refresh timer (every 60s) for freshness indicator
        self._timer = QTimer(self)
        self._timer.timeout.connect(self.refresh_ui)
        self._timer.start(30000)

        self._apply_active_empty()

    def _apply_active_empty(self):
        self.capacity_bar.setValue(0)
        self.capacity_bar.setVisible(False)
        self.capacity_pct.setText("UNKNOWN")
        self.capacity_pct.setStyleSheet("color: #8B949E; font-family: 'Segoe UI'; font-size: 12px; font-weight: 600; border: none;")

    def refresh_ui(self):
        try:
            metas = self.app_service.list_apis()
            self.count_lbl.setText(str(len(metas)))
            active = self.app_service.get_active()
            active_id = active.id if active else None

            # Update active card
            if active:
                self.active_name.setText(active.name)
                self.active_provider.setText(active.provider)
                self.active_dot.setStyleSheet("color: #1A7F37; font-size: 8px; border: none;")
                cap = active.capacity
                if cap.is_unknown():
                    self.capacity_pct.setText("UNKNOWN")
                    self.capacity_pct.setStyleSheet("color: #8B949E; font-family: 'Segoe UI'; font-size: 12px; font-weight: 600; border: none;")
                    self.capacity_bar.setVisible(False)
                else:
                    self.capacity_pct.setText(f"{cap.percent}%")
                    self.capacity_pct.setStyleSheet("color: #1F2328; font-family: 'Segoe UI'; font-size: 12px; font-weight: 700; border: none;")
                    self.capacity_bar.setValue(cap.percent)
                    self.capacity_bar.setVisible(True)
                    # Color gradient by capacity: high = dark, low = red
                    if cap.percent >= 50:
                        col = "#1F2328"
                    elif cap.percent >= 20:
                        col = "#9A6700"
                    else:
                        col = "#CF222E"
                    self.capacity_bar.setStyleSheet(f"""
                        QProgressBar {{ background: #EAEEF2; border: none; border-radius: 4px; }}
                        QProgressBar::chunk {{ background: {col}; border-radius: 4px; }}
                    """)
            else:
                # No active: show best candidate as hint? Or keep empty.
                # Show empty state
                self.active_name.setText("— No active API —")
                self.active_provider.setText("Add an API and press SWITCH")
                self.active_dot.setStyleSheet("color: #8B949E; font-size: 8px; border: none;")
                self.capacity_pct.setText("UNKNOWN")
                self.capacity_pct.setStyleSheet("color: #8B949E; font-family: 'Segoe UI'; font-size: 12px; font-weight: 600; border: none;")
                self.capacity_bar.setVisible(False)
                if metas:
                    # hint best candidate
                    best = self.app_service.selection.select_best(metas)
                    if best:
                        self.active_provider.setText(f"Next: {best.name} ({best.provider})")

            # Populate list
            self.api_list.clear()
            if not metas:
                item = QListWidgetItem()
                item.setFlags(item.flags() & ~Qt.ItemIsSelectable)
                self.api_list.addItem(item)
                # placeholder widget
                ph = QLabel("No APIs yet.\nClick \"+ Add API\" to get started.")
                ph.setStyleSheet("color: #8B949E; font-family: 'Segoe UI'; font-size: 12px; background: #FFFFFF; border: 1px dashed #D0D7DE; border-radius: 8px; padding: 18px;")
                ph.setAlignment(Qt.AlignCenter)
                self.api_list.setItemWidget(item, ph)
                item.setSizeHint(ph.sizeHint())
            else:
                # sort for display: ranked order
                ranked = self.app_service.selection.rank(metas)
                # ranked + any not ranked? rank already filters invalid/cooldown to end? We'll show all, ranked first then invalid
                # Show ranked first, then remaining invalid/cooldown greyed
                all_ids_ranked = {m.id for m in ranked}
                remaining = [m for m in metas if m.id not in all_ids_ranked]
                display_order = ranked + remaining
                for meta in display_order:
                    is_active = (active_id == meta.id)
                    item = QListWidgetItem()
                    row = ApiRowWidget(meta, is_active, self.on_remove_api, self.on_copy_fingerprint)
                    item.setSizeHint(row.sizeHint())
                    self.api_list.addItem(item)
                    self.api_list.setItemWidget(item, row)

            # Switch button enabled?
            has_eligible = any(not m.is_invalid() and not m.is_in_cooldown() for m in metas)
            self.switch_btn.setEnabled(has_eligible and not self._switching)
            if not has_eligible and metas:
                self.switch_status.setText("No eligible APIs (all invalid or in cooldown).")
            elif self._switching:
                pass  # keep current status
            else:
                if not self._switching:
                    self.switch_status.setText("")

        except Exception as e:
            logger.exception("refresh_ui failed")
            QMessageBox.warning(self, "Refresh failed", str(e))

    def background_refresh(self):
        # Run stale capacity refresh in background thread (currently no network)
        try:
            # Use QThread for consistency
            self._refresh_thread = QThread(self)
            self._refresh_worker = RefreshWorker(self.app_service)
            self._refresh_worker.moveToThread(self._refresh_thread)
            self._refresh_thread.started.connect(self._refresh_worker.run)
            self._refresh_worker.finished.connect(self._refresh_thread.quit)
            self._refresh_worker.finished.connect(self._refresh_worker.deleteLater)
            self._refresh_thread.finished.connect(self._refresh_thread.deleteLater)
            self._refresh_worker.finished.connect(self.refresh_ui)
            self._refresh_thread.start()
        except Exception:
            pass

    # --- Handlers ---
    def on_add_api(self):
        dlg = AddApiDialogInline(self)
        if dlg.exec() == QDialog.Accepted:
            name, provider, key = dlg.get_values()
            try:
                self.app_service.add_api(name=name, provider=provider, api_key=key)
                self.refresh_ui()
                QMessageBox.information(self, "Added", f"API '{name}' added successfully.\n\nSecret stored securely via {self.app_service.credential_store.backend}.")
            except Exception as e:
                QMessageBox.critical(self, "Add failed", str(e))

    def on_remove_api(self, meta: CredentialMeta):
        ret = QMessageBox.question(self, "Remove API", f"Remove '{meta.name}'?\nSecret will be deleted from secure store.", QMessageBox.Yes | QMessageBox.No, QMessageBox.No)
        if ret == QMessageBox.Yes:
            try:
                self.app_service.remove_api(meta.id)
                self.refresh_ui()
            except Exception as e:
                QMessageBox.critical(self, "Remove failed", str(e))

    def on_copy_fingerprint(self, meta: CredentialMeta):
        # Show fingerprint, not real key
        secret = self.app_service.credential_store.get(meta.id)
        fp = fingerprint(secret) if secret else "••••••••"
        cb = QApplication.clipboard()
        cb.setText(fp)
        self.switch_status.setText(f"Fingerprint for {meta.name}: {fp} (copied)")
        QTimer.singleShot(2500, lambda: self.switch_status.setText(""))

    def on_switch(self):
        if self._switching:
            return
        self._switching = True
        self.switch_btn.setEnabled(False)
        self.switch_btn.setText("SWITCHING…")
        self.switch_status.setText("Selecting best API…")
        self.switch_status.setStyleSheet("color: #0969DA; font-family: 'Segoe UI'; font-size: 11px; border: none;")

        self._switch_thread = QThread(self)
        self._switch_worker = SwitchWorker(self.app_service)
        self._switch_worker.moveToThread(self._switch_thread)
        self._switch_thread.started.connect(self._switch_worker.run)
        self._switch_worker.progress.connect(self._on_switch_progress)
        self._switch_worker.finished.connect(self._on_switch_finished)
        self._switch_worker.error.connect(self._on_switch_error)
        self._switch_worker.finished.connect(self._switch_thread.quit)
        self._switch_worker.error.connect(self._switch_thread.quit)
        self._switch_worker.finished.connect(self._switch_worker.deleteLater)
        self._switch_worker.error.connect(self._switch_worker.deleteLater)
        self._switch_thread.finished.connect(self._switch_thread.deleteLater)
        self._switch_thread.start()

    def _on_switch_progress(self, msg: str):
        self.switch_status.setText(msg[:120])

    def _on_switch_finished(self, result):
        self._switching = False
        self.switch_btn.setText("SWITCH")
        self.refresh_ui()
        if result.success:
            self.switch_status.setText(f"✓ {result.message}")
            self.switch_status.setStyleSheet("color: #1A7F37; font-family: 'Segoe UI'; font-size: 11px; font-weight: 600; border: none;")
            QTimer.singleShot(4000, lambda: self.switch_status.setText(""))
        else:
            self.switch_status.setText(f"✗ {result.message}")
            self.switch_status.setStyleSheet("color: #CF222E; font-family: 'Segoe UI'; font-size: 11px; border: none;")
            QMessageBox.warning(self, "Switch failed", result.message)

    def _on_switch_error(self, msg: str):
        self._switching = False
        self.switch_btn.setText("SWITCH")
        self.switch_status.setText(f"Error: {msg[:120]}")
        self.switch_status.setStyleSheet("color: #CF222E; font-family: 'Segoe UI'; font-size: 11px; border: none;")
        QMessageBox.critical(self, "Switch error", msg)
        self.refresh_ui()

    def on_diagnostics(self):
        try:
            from app.ui.dialogs import DiagnosticsDialog
            diag = self.app_service.get_diagnostics()
            dlg = DiagnosticsDialog(diag, self)
            dlg.exec()
        except Exception as e:
            QMessageBox.information(self, "Diagnostics", f"Diagnostics error: {e}")

    def on_more(self):
        m = QMenu(self)
        m.setStyleSheet("QMenu { font-family: 'Segoe UI'; font-size: 12px; }")
        act_diag = QAction("Diagnostics…", self)
        act_refresh = QAction("Refresh", self)
        act_about = QAction("About", self)
        m.addAction(act_diag)
        m.addAction(act_refresh)
        m.addSeparator()
        m.addAction(act_about)
        pos = self.more_btn.mapToGlobal(self.more_btn.rect().bottomLeft())
        act = m.exec(pos)
        if act == act_diag:
            self.on_diagnostics()
        elif act == act_refresh:
            self.refresh_ui()
            self.background_refresh()
        elif act == act_about:
            QMessageBox.about(self, "About", "OpenCode API Manager v1.0.0\n\nDeterministic credential manager for OpenCode.\nNo AI, no telemetry, local-only.\n\nProvider: OpenCode Zen / OpenCode Go\nStorage: Windows Credential Manager / DPAPI\n\nhttps://github.com/anomalyco/opencode")
