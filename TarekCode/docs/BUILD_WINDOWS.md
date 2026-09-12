# Build Windows

Produces `dist/OpenCodeAPIManager.exe` — double-click, no installer, no Python/Node required, no console.

## Prerequisites

- Windows 10/11
- Python 3.10+ (3.11 recommended)
- PowerShell 5.1+

## Steps

### 1. Create venv & install

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
pip install pyinstaller
```

### 2. Build

Option A: PowerShell script (recommended)

```powershell
.\build\build_windows.ps1
# or with custom name
.\build\build_windows.ps1 -Name "OpenCodeAPIManager"
```

Option B: Batch

```cmd
build\build.bat
```

Option C: Direct PyInstaller

```powershell
pyinstaller --noconfirm --windowed --name OpenCodeAPIManager `
  --icon assets\icon.ico `
  --add-data "assets;assets" `
  --hidden-import win32crypt --hidden-import keyring.backends.Windows `
  --collect-submodules PySide6 `
  src\app\main.py
```

### 3. Output

```text
dist/
  OpenCodeAPIManager.exe   (≈ 60-80 MB, includes PySide6 + Python)
build/
  ...
```

Test on clean Windows without Python:

```powershell
dist\OpenCodeAPIManager.exe
```

It should open immediately, show Diagnostics (`⚙` → Diagnostics) with:

```
OpenCode ✓ Installed
Version ✓ x.x.x
Auth integration ✓ Supported
Usage information — Not available
```

## PyInstaller Flags Explained

- `--windowed` → no console (spec §29)
- `--name OpenCodeAPIManager` → exe name
- `--icon assets/icon.ico` → optional, fallback if missing → no icon
- `--collect-submodules PySide6` → ensure Qt plugins captured
- `datas` in `.spec` → includes `assets/`

## Troubleshooting

- **Missing pywin32:** `pip install pywin32` is optional; DPAPI fallback uses `ctypes` so build still works.
- **keyring backend not found:** `keyring` ≥24 includes Windows backend; ensure `keyring.backends.Windows` hidden import.
- **One-file vs one-dir:** Script builds `onefile` by default (single EXE). For faster startup, change to `one-dir` in `build_windows.ps1`.

## Code Signing (Optional)

For distribution, sign the exe:

```powershell
signtool sign /fd SHA256 /a /t http://timestamp.digicert.com dist\OpenCodeAPIManager.exe
```
