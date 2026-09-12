# Security

## Principles

- **Local only.** No credentials leave the device except to OpenCode/provider when required for verification or activation. No telemetry, no analytics, no remote DB, no cloud sync.
- **No AI.** No LLM, no embeddings, no prompts, no cloud AI service.
- **No secret ever displayed.** UI shows `••••••••` or fingerprint `sk-••••...a3f9` (non-usable, last 4 chars only). Logs redact `sk-` patterns. Subprocess arguments never contain raw key if avoidable (file write path used instead of CLI arg).
- **No invention.** `UNKNOWN` capacity is not guessed.

## Storage

### Secrets

Preferred: **Windows Credential Manager** via `keyring` library

- Service: `OpenCodeAPIManager`
- Account: `<credential_id>` (e.g., `f13b7b...`)
- Keyring backend on Windows uses `WinVaultKeyring` (Credential Manager)

Fallback: **DPAPI** encrypted file

- Path: `%APPDATA%\OpenCodeAPIManager\secrets\<safe_id>.bin`
- Encryption: `CryptProtectData` (pywin32 `win32crypt.CryptProtectData` if available, else `ctypes` `CryptProtectData` with `CRYPTPROTECT_UI_FORBIDDEN`)
- Payload is `base64(encrypted_bytes)` — not plain text
- Decryption uses user-scope DPAPI, so only same Windows user can decrypt

No other fallback (not plain text). If both fail, error is surfaced; metadata not saved without secret.

### Metadata (non-secret)

- Path: `%APPDATA%\OpenCodeAPIManager\accounts.json`
  ```json
  {
    "<id>": {
      "id": "<id>",
      "name": "Personal 01",
      "provider": "opencode",
      "capacity": {"percent": null, "source": "unknown", "timestamp": null},
      "auth_status": "READY",
      "last_used_at": "...",
      "cooldown_until": null,
      "failure_count": 0
    }
  }
  ```
- Also `settings.json`, `state.json` (active credential id)

No secrets inside `accounts.json`. Verified by tests (`test_secret_redaction`).

Atomic writes: write to temp file in same directory + `replace()` to prevent corruption.

### OpenCode Files

- `~/.local/share/opencode/auth.json` — contains the active provider key (single `opencode` entry). Written by `OpenCodeAdapter.activate()` via atomic JSON write. This is the canonical store OpenCode reads.
- `~/.local/share/opencode/account.json` — mirrored if it exists.

## Redaction

`app/utils/redaction.py`:

- `redact_text(s)` → replaces `sk-[A-Za-z0-9_\-]{8,}` with `sk-••••••••`
- `redact_dict(d)` → replaces any dict key containing `key`/`secret`/`token` with `••••••••`, else recurses and redacts string values
- `fingerprint(key)` → `sk-••••...<last4>` (non-usable)

All exception messages, logs, `auth list` outputs, and diagnostics are passed through `redact_text` before logging/display.

## Network

External connections only when:

- `adapter.verify()` runs `opencode auth list` (local subprocess, no network)
- Future `adapter.get_usage()` would call provider API with `Authorization: Bearer <key>` — currently disabled (returns UNKNOWN) because no endpoint exists
- No background phone home.

## Threat Considerations

- **Memory:** Secrets are held in Python `str` briefly; not zeroed (CPython limitation). Mitigated by not logging and shortest live time.
- **Swap/file leakage:** DPAPI encrypted file is the only on-disk copy besides Credential Manager.
- **Other users:** DPAPI user scope + Credential Manager user vault restrict to same Windows account.
- **Backup:** If user copies `%APPDATA%\OpenCodeAPIManager\` to another machine, secrets file is not decryptable there (DPAPI machine/user bound). Recommend re-adding APIs on new device.
