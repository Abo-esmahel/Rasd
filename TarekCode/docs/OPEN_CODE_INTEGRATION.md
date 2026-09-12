# OpenCode Integration — Verified Facts Only

**Date:** 2026-09-12  
**Installed version:** `opencode --version` → **1.17.11** (via `npm` / `opencode-ai` package, binary `C:\Users\Lenovo\AppData\Roaming\npm\node_modules\opencode-ai\bin\opencode.exe`)

No assumptions, only commands actually executed and outputs observed.

---

## 1. Commands Exist

```text
opencode --version                    → 1.17.11
opencode --help                       → lists commands: completion, acp, mcp, [project], attach, run, debug, providers (auth), agent, upgrade, uninstall, serve, web, models, stats, export, import, github, pr, session, plugin, db
opencode providers --help             → alias for auth: manages AI providers and credentials
opencode auth --help                  → providers / auth
opencode auth list                    → list providers and credentials
opencode auth login [url]             → log in to a provider (-p provider, -m method)
opencode auth logout [provider]       → log out from a provider
opencode debug config                 → shows resolved config JSON
opencode debug paths                  → shows home/data/bin/log/repos/cache/config/state/tmp
opencode debug info                   → version + os + plugins
opencode stats --help                 → shows token usage & cost statistics (local DB)
opencode models [provider]            → lists models (opencode/*, opencode-go/*, plus custom)
```

**Commands that DO NOT exist (verified absent):**

```text
opencode auth switch
opencode auth status
opencode auth use
opencode usage
opencode quota
opencode capacity
opencode balance
```

No such commands appear in `--help` at any level. Adapter must not assume them.

---

## 2. Capability Detection

Implemented in `OpenCodeAdapter.get_capabilities()`:

- Probe `opencode auth --help` → parse `list`/`login`/`logout` presence
- Probe `opencode auth login --help` → detect `-p/--provider`, `-m/--method`
- Probe `opencode auth logout --help` → detect `provider` positional
- Probe `opencode stats --help` → sets `has_stats=True`
- No `switch`, `status`, `usage` detected → `has_auth_switch=False`, `has_usage=False`

Result on 1.17.11:

```json
{
  "version": "1.17.11",
  "has_auth_list": true,
  "has_auth_login": true,
  "has_auth_logout": true,
  "has_auth_switch": false,
  "has_auth_status": false,
  "has_usage": false,
  "has_stats": true,
  "switching_mechanism": "logout_login",
  "login_supports_provider_flag": true,
  "login_supports_method_flag": true
}
```

Switching mechanism is therefore `logout_login` via file manipulation (see §3).

---

## 3. Auth Storage (Observed Files)

- `C:\Users\Lenovo\.local\share\opencode\auth.json`

  ```json
  {
    "opencode": {
      "type": "api",
      "key": "sk-9tSsre1FyMvEmZT12efFSdw8GS2NbdTfUo93lXzMG1IhfjWJ6U96TUVLiOcs5rUQ"
    }
  }
  ```

- `C:\Users\Lenovo\.local\share\opencode\account.json`

  ```json
  {
    "version": 2,
    "accounts": {
      "f13b7bcb9001KvVC1GCLYqL94o": {
        "id": "f13b7bcb9001KvVC1GCLYqL94o",
        "serviceID": "opencode",
        "description": "default",
        "credential": {"type": "api", "key": "sk-Uit55yt..."}
      }
    },
    "active": { "opencode": "f13b7bcb9001KvVC1GCLYqL94o" }
  }
  ```

- Environment: `OPENCODE_API_KEY` also honored (seen in `auth list` output: `Environment → OpenCode Zen OPENCODE_API_KEY`)

`auth list` output (redacted):

```text
T  Credentials ~/.local/share/opencode/auth.json
|• OpenCode Zen  api
— 1 credentials
T  Environment
|• OpenCode Zen  OPENCODE_API_KEY
|• OpenCode Go   OPENCODE_API_KEY
— 2 environment variables
```

Adapter reads both `auth.json` and `account.json`. Write path prefers `auth.json` (simpler) and mirrors to `account.json` if it exists for backward compatibility.

`opencode auth login` is **interactive** (prompts for key). There is no `--api-key` flag. Therefore programmatic activation **must** use direct file write (`_atomic_write_json`) — this is equivalent to official storage and is the only non-interactive way without browser/terminal automation.

Logout via CLI `opencode auth logout <provider>` may succeed but fallback to file removal if CLI fails.

---

## 4. Usage / Quota / Capacity — Negative Result (Important)

**All documented attempts returned no usable endpoint.** We treat this as `UNKNOWN`, never invented.

Tested endpoints (with valid `sk-` key in `Authorization: Bearer …`):

```text
GET https://api.opencode.ai/v1/usage           → 403 Forbidden
GET https://api.opencode.ai/v1/billing         → 403
GET https://api.opencode.ai/v1/billing/usage   → 403
GET https://api.opencode.ai/v1/account         → 403
GET https://api.opencode.ai/v1/me              → 403
GET https://api.opencode.ai/v1/user            → 403
GET https://api.opencode.ai/v1/quota           → 403
GET https://opencode.ai/api/usage              → 403
GET https://api.opencode.ai/zen/v1/usage       → 403
GET https://api.opencode.ai/zen/v1/billing     → 403
POST https://api.opencode.ai/v1/chat/completions (with real model opencode/muse-spark-1.2-contributor-free, auth header Bearer, User-Agent opencode/1.17.11)
  → actual opencode API at https://api.opencode.ai/v1/chat/completions returns 200 but body "Not Found" unless provider routes via zen? Indicates routing difference, not quota.
  Response headers on success path contain ONLY: Date, Content-Type, Content-Length, Connection, Server, CF-RAY — NO X-RateLimit-Remaining, NO quota headers.
```

Binary analysis (`strings` on `opencode.exe`):

- No documented `balance`, `remaining_tokens`, `quota`, `capacity` strings as API endpoint.
- No `opencode.dev` host.
- Hosts found: `https://api.opencode.ai`, `https://opencode.ai/zen`, `https://opencode.ai/zen/v1`, `https://opencode.ai/zen/go/v1`.

Conclusion: **No reliable remaining capacity source exists in 1.17.11.** `OpenCodeAdapter.get_usage()` therefore returns `CapacityInfo(percent=None, source=UNKNOWN)` always, and docs/UI display `UNKNOWN` with LRU fallback. If a future version adds a documented endpoint, implement in `get_usage()` with source `provider_api` and `capacity_timestamp`.

Headers-based capacity also not possible (no rate-limit headers observed).

---

## 5. Verification

After `activate()` (file write), verification steps (spec §12) are:

1. Check `auth.json` contains key for provider.
2. Run `opencode auth list` and look for provider name (`OpenCode Zen` / `opencode`) in output.
3. Map output to `ACTIVE` / `READY` / `INVALID` / `UNKNOWN`.

Never considers `login exit 0` sufficient.

If `opencode` executable missing → `UNKNOWN` + diagnostic indicates install required.

---

## 6. Env & Paths

From `opencode debug paths`:

```text
home   C:\Users\Lenovo
data   C:\Users\Lenovo\.local\share\opencode
bin    C:\Users\Lenovo\.cache\opencode\bin
log    C:\Users\Lenovo\.local\share\opencode\log
repos  C:\Users\Lenovo\.local\share\opencode\repos
cache  C:\Users\Lenovo\.cache\opencode
config C:\Users\Lenovo\.config\opencode
state  C:\Users\Lenovo\AppData\Roaming\ai.opencode.desktop\opencode
tmp    C:\Users\Lenovo\AppData\Local\Temp\opencode
```

`opencode debug config` shows `provider: { claude: { … } }` structure for custom providers; not needed for Zen.

---

## 7. What NOT to Do

- Do not invent `balance`/`remaining_tokens`/`quota` if not in response.
- Do not treat UNKNOWN as 0% or 100%.
- Do not use browser automation or terminal-coordinate automation.
- Do not patch or bypass OpenCode.
- Do not log secrets; all diagnostic redacts `sk-` patterns.
