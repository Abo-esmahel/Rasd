# OpenCode API Manager

**Windows Desktop Utility** — إدارة احترافية لـ OpenCode API credentials بدون AI، بدون تخمين، بدون تعديل لـ OpenCode نفسه.

- اختيار **أفضل API** تلقائيًا (أكبر Remaining Capacity أو Least Recently Used)
- تفعيل تلقائي via OpenCode CLI adapter (logout/login برمجيًا)
- تخزين آمن عبر **Windows Credential Manager / DPAPI**
- واجهة **compact** احترافية تشبه أدوات Windows الرسمية
- لا ترسل أي credentials لخارج الجهاز — local فقط

## Verified Facts (OpenCode 1.17.11)

تم فحص النسخة المثبتة فعليًا:

```
opencode --version          → 1.17.11
opencode auth --help        → has: list, login, logout  (NO switch/status/use)
opencode auth login --help  → supports -p provider, -m method, NO --api-key flag
opencode auth list          → prints decorative list + env vars
auth storage                → ~/.local/share/opencode/auth.json  { "opencode": {"type":"api","key":"..."} }
                           → ~/.local/share/opencode/account.json { version:2, accounts:{...}, active:{...} }
opencode stats              → token usage local DB, NOT provider quota
provider API /usage         → 403 Forbidden (لا يوجد endpoint موثق)
response headers            → لا تحتوي rate-limit info
```

النتيجة: **Capacity = UNKNOWN** دائمًا، والاختيار يقع على **Least Recently Used** (موثق في `docs/OPEN_CODE_INTEGRATION.md`).

## Quick Start (Developer)

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
# تشغيل مباشر بدون EXE
$env:PYTHONPATH="src"
python -m app.main
# أو
python src/app/main.py
```

## Build EXE

```powershell
.\build\build_windows.ps1
# الناتج: dist/OpenCodeAPIManager.exe  (--windowed, لا يحتاج Python/Node)
```

انظر `docs/BUILD_WINDOWS.md`.

## Architecture

```
src/app/
  domain/        Models, State Machine
  infrastructure/ SecureStore + MetadataStore
  opencode/      OpenCodeAdapter + Capability Detection (sole CLI gateway)
  selection/     SelectionEngine (deterministic ranking)
  services/      ApplicationService (Switch + Failover)
  ui/            PySide6 MainWindow + Dialogs
  utils/         Redaction + Time
```

الطبقات:
```
UI
 ↓
ApplicationService
 ↓
SelectionEngine
 ↓
OpenCodeAdapter
 ↓
OpenCode CLI (opencode auth list/logout/login + auth.json)
```

لا تقوم UI بتنفيذ `subprocess` أبدًا.

## Selection Algorithm

```
1. احذف credentials غير صالحة (INVALID/UNAUTHORIZED)
2. احذف من في cooldown
3. إن وجدت reliable capacity (percent + source + timestamp fresh):
      أعلى percent يفوز
      تعادل → أقدم last_used_at (LRU)
4. إن لم توجد reliable capacity:
      أقدم last_used_at يفوز
```

`UNKNOWN` لا تعتبر 0% ولا 100% ولا تقدَّر عشوائيًا.

## Security

- Secrets فقط في **Windows Credential Manager** (keyring) أو **DPAPI** ملف مشفر.
- `accounts.json` لا يحتوي أسرار إطلاقًا (metadata فقط في `%APPDATA%\OpenCodeAPIManager\`).
- لا telemetry، لا cloud sync، لا يرسل credentials لخادم خارجي.
- Logs/UI تعرض فقط `••••••••` أو fingerprint غير قابل للاستخدام.

## Testing

```powershell
pytest -q
pytest tests/test_selection.py -v
```

## Docs

- `docs/ARCHITECTURE.md`
- `docs/OPEN_CODE_INTEGRATION.md`  — حقائق تم التحقق منها فقط
- `docs/SELECTION.md`
- `docs/SECURITY.md`
- `docs/CACHING.md`
- `docs/BUILD_WINDOWS.md`
- `docs/UI.md`
