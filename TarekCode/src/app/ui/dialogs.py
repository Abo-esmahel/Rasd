"""
Additional dialogs for UI
"""
from __future__ import annotations

from PySide6.QtCore import Qt
from PySide6.QtGui import QFont
from PySide6.QtWidgets import QDialog, QVBoxLayout, QLabel, QPushButton, QGridLayout, QFrame, QHBoxLayout

class DiagnosticsDialog(QDialog):
    def __init__(self, diagnostics: dict, parent=None):
        super().__init__(parent)
        self.setWindowTitle("Diagnostics")
        self.setModal(True)
        self.setFixedSize(420, 380)
        self.setStyleSheet("""
            QDialog { background: #FFFFFF; }
            QLabel { font-family: 'Segoe UI'; }
        """)
        root = QVBoxLayout(self)
        root.setContentsMargins(18, 16, 18, 16)
        root.setSpacing(12)

        title = QLabel("Diagnostics")
        tf = QFont("Segoe UI", 11)
        tf.setBold(True)
        title.setFont(tf)
        title.setStyleSheet("color: #1F2328;")
        root.addWidget(title)

        subtitle = QLabel("Settings → Diagnostics  •  Verifies real OpenCode integration")
        subtitle.setStyleSheet("color: #656D76; font-size: 11px;")
        subtitle.setWordWrap(True)
        root.addWidget(subtitle)

        grid = QGridLayout()
        grid.setHorizontalSpacing(12)
        grid.setVerticalSpacing(6)

        caps = diagnostics.get("capabilities", {}) if diagnostics else {}
        version = diagnostics.get("version", "unknown")
        installed = diagnostics.get("installed", False)

        def row_widget(key: str, value: str, ok: bool | None):
            k = QLabel(key)
            k.setStyleSheet("color: #1F2328; font-size: 12px;")
            v = QLabel(value)
            if ok is True:
                v.setStyleSheet("color: #1A7F37; font-size: 12px; font-weight: 600;")
                icon = QLabel("✓")
                icon.setStyleSheet("color: #1A7F37; font-weight: 700;")
            elif ok is False:
                v.setStyleSheet("color: #CF222E; font-size: 12px; font-weight: 600;")
                icon = QLabel("✗")
                icon.setStyleSheet("color: #CF222E; font-weight: 700;")
            else:
                v.setStyleSheet("color: #8B949E; font-size: 12px;")
                icon = QLabel("—")
                icon.setStyleSheet("color: #8B949E;")
            return k, icon, v

        rows = []
        rows.append(row_widget("OpenCode", "Installed" if installed else "Not found", installed))
        rows.append(row_widget("Version", version, installed))
        rows.append(row_widget("Auth integration", caps.get("switching_mechanism", "unknown"), caps.get("has_auth_list")))
        # Usage is expected to be NOT available (honest UNKNOWN per §5)
        usage_ok = diagnostics.get("usage_available", False)
        rows.append(row_widget("Usage information", "Available" if usage_ok else "Not available", None if not usage_ok else True))
        # Show explicitly if unknown we display (— Not available) per spec §22
        rows.append(row_widget("Capacity source", diagnostics.get("usage_source", "none"), None))

        for i, (k, icon, v) in enumerate(rows):
            grid.addWidget(k, i, 0)
            grid.addWidget(icon, i, 1)
            grid.addWidget(v, i, 2, alignment=Qt.AlignRight)

        # Auth JSON existence
        ak_exists = diagnostics.get("auth_json_exists", False)
        ag_exists = diagnostics.get("account_json_exists", False)
        k1, ic1, v1 = row_widget("auth.json", "Found" if ak_exists else "Not found", ak_exists)
        k2, ic2, v2 = row_widget("account.json", "Found" if ag_exists else "Not found", None)
        grid.addWidget(k1, len(rows), 0)
        grid.addWidget(ic1, len(rows), 1)
        grid.addWidget(v1, len(rows), 2, alignment=Qt.AlignRight)
        grid.addWidget(k2, len(rows)+1, 0)
        grid.addWidget(ic2, len(rows)+1, 1)
        grid.addWidget(v2, len(rows)+1, 2, alignment=Qt.AlignRight)

        root.addLayout(grid)

        # Data dir
        dir_lbl = QLabel(f"Data: {diagnostics.get('data_dir','')}")
        dir_lbl.setStyleSheet("color: #8B949E; font-size: 10px;")
        dir_lbl.setWordWrap(True)
        root.addWidget(dir_lbl)

        note = QLabel("Capacity = UNKNOWN is expected: OpenCode/provider has no documented usage/quota endpoint (verified 1.17.11). The app uses Least Recently Used.")
        note.setStyleSheet("color: #656D76; font-size: 11px; background: #F6F8FA; border: 1px solid #D0D7DE; border-radius: 6px; padding: 8px;")
        note.setWordWrap(True)
        root.addWidget(note)

        # Close
        close_btn = QPushButton("Close")
        close_btn.setFixedHeight(32)
        close_btn.setStyleSheet("""
            QPushButton { background: #1F2328; color: #FFFFFF; border-radius: 6px; padding: 0 16px; font-weight: 600; }
            QPushButton:hover { background: #2D333B; }
        """)
        close_btn.clicked.connect(self.accept)
        hl = QHBoxLayout()
        hl.addStretch()
        hl.addWidget(close_btn)
        root.addLayout(hl)
