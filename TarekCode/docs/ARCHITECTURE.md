# Architecture

## Overview

```
src/app/
  domain/          → Pure domain models (no IO)
  credentials/     → Reserved (future custom provider schemas)
  infrastructure/  → SecureStore (DPAPI/keyring) + MetadataStore (JSON)
  opencode/        → OpenCodeAdapter + Capabilities (sole CLI boundary)
  selection/       → SelectionEngine (deterministic, stateless)
  services/        → ApplicationService (orchestrates Switch/failover)
  ui/              → PySide6 MainWindow, dialogs, workers
  utils/           → redaction, time helpers
  models/          → Reserved
```

## Layering (strict)

```
UI
 ↓ (calls only ApplicationService)
ApplicationService
 ↓ (calls SelectionEngine + OpenCodeAdapter + stores)
SelectionEngine  OpenCodeAdapter  SecureStore  MetadataStore
 ↓                  ↓
             OpenCode CLI / auth.json / %APPDATA%
```

- `src/app/ui/*` must NEVER call `subprocess` directly. All OpenCode interaction passes through `src/app/opencode/adapter.py`.
- Domain has no dependency on infrastructure or UI.
- SelectionEngine is stateless and pure: input list → ranked list.

## Data Flow: Add API

```
User: + Add API → Dialog(name, provider, key)
  → ApplicationService.add_api()
    → CredentialStore.save(id, secret)  // keyring or DPAPI
    → MetadataStore.upsert(meta)        // accounts.json (no secret)
    → UI refresh (background)
```

## Data Flow: Switch

```
User: [SWITCH]
  → MainWindow spawns SwitchWorker (QThread)
    → ApplicationService.switch()
      → load candidates from MetadataStore
      → SelectionEngine.rank()  // filters invalid/cooldown, sorts
      → for each candidate in ranked:
            secret = CredentialStore.get(id)
            adapter.activate(provider, secret)  // file write to auth.json (+ account.json)
            verification = adapter.verify()      // auth list + file check
            if ACTIVE/READY → success, update last_used_at, set state.json
            else classify failure, cooldown, try next (max_attempts, tried_set)
      → return SwitchResult
    → UI _on_switch_finished() → refresh + message
```

## State Machine (per credential)

```
UNKNOWN → CHECKING → READY → ACTIVE
                 ↘ INVALID / UNAUTHORIZED / FAILED
RATE_LIMITED → COOLDOWN → READY (after cooldown_until)
STALE → (refresh) → FRESH or UNKNOWN
```

Stored in `accounts.json` as `auth_status`, `rate_limit_status`, `cooldown_until`, `failure_count`.

## Storage Locations

- Secrets: `Windows Credential Manager` (keyring, service=OpenCodeAPIManager, account=<id>) fallback → `%APPDATA%\OpenCodeAPIManager\secrets\<id>.bin` (DPAPI encrypted)
- Metadata: `%APPDATA%\OpenCodeAPIManager\accounts.json`
- Settings: `%APPDATA%\OpenCodeAPIManager\settings.json`
- State (active id): `%APPDATA%\OpenCodeAPIManager\state.json`
- OpenCode auth: `%USERPROFILE%\.local\share\opencode\auth.json` + `account.json`

## Concurrency

- `ApplicationService._switch_lock` (threading.Lock) prevents overlapping switches (spec §27)
- UI disables SWITCH button while `_switching == True`
- Single instance (§28) via `QLockFile` at `%TEMP%\OpenCodeAPIManager.lock`

## Performance

- UI appears immediately; background refresh via QThread/Runnable (spec §18)
- No network requests on Switch unless stale capacity needs refresh (currently no network, so instant)
- Atomic JSON writes (temp file + rename) to avoid corruption.
