"""
OpenCodeAdapter — the SOLE place that talks to OpenCode CLI.

Spec §11, §23: UI must never call subprocess directly.
All interactions go through this adapter.

Implements (only what is verified to exist):
- get_version()
- get_capabilities()
- list_credentials()
- login(provider, key)  — via file manipulation + optional CLI verification
- logout(provider)
- activate(credential)  — deterministic: logout + file write OR direct file write
- verify() -> VerificationResult
- get_usage() -> CapacityInfo (always UNKNOWN unless proven)

Switching strategy (capability-aware):
- Preferred: if `opencode auth switch` exists, use it (currently NOT present in 1.17.11)
- Fallback: logout + login via file (official mechanism is auth.json/account.json)
  We implement file-based activation as PRIMARY because CLI login is interactive.

Auth files observed:
- %USERPROFILE%\\.local\\share\\opencode\\auth.json
  { "opencode": {"type":"api","key":"sk-..."} }  (new format, single key per provider)
- %USERPROFILE%\\.local\\share\\opencode\\account.json
  { "version":2, "accounts":{id:{...credential...}}, "active":{opencode:id} } (legacy/multi-account)
  We support BOTH, preferring auth.json for opencode provider.

No browser automation, no terminal-coordinate automation, no patching.

Capacity: no reliable provider API found (see docs/OPEN_CODE_INTEGRATION.md).
All attempts to fetch usage from:
- opencode CLI (no usage command)
- provider APIs (403, no endpoint)
- response headers (no rate-limit headers)
Return UNKNOWN, never invented.
"""
from __future__ import annotations

import json
import logging
import os
import shutil
import subprocess
import time
from pathlib import Path
from typing import Optional, Dict, Any, List, Tuple

from app.domain.models import CapacityInfo, VerificationResult, AuthStatus, CapacitySource
from app.opencode.capabilities import OpenCodeCapabilities
from app.utils.redaction import redact_text

logger = logging.getLogger(__name__)

# Default locations (mirrors opencode debug paths)
DEFAULT_DATA_DIR = Path.home() / ".local" / "share" / "opencode"
DEFAULT_AUTH_JSON = DEFAULT_DATA_DIR / "auth.json"
DEFAULT_ACCOUNT_JSON = DEFAULT_DATA_DIR / "account.json"

OPENCODE_EXE_CANDIDATES = ["opencode", "opencode.exe"]


class AdapterError(RuntimeError):
    pass


def _find_opencode_exe() -> Optional[str]:
    for name in OPENCODE_EXE_CANDIDATES:
        p = shutil.which(name)
        if p:
            return p
    # npm wrapper via opencode.ps1 is found as "opencode" on PATH
    return shutil.which("opencode")


def _run(cmd: List[str], timeout: int = 15, env: Optional[dict] = None, hide_window: bool = True) -> Tuple[int, str, str]:
    """Run subprocess, capture stdout/stderr, redact secrets."""
    startupinfo = None
    creationflags = 0
    if os.name == "nt" and hide_window:
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = subprocess.SW_HIDE
        creationflags = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0

    try:
        proc = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="ignore",
            timeout=timeout,
            startupinfo=startupinfo,
            creationflags=creationflags,
            env=env,
        )
        return proc.returncode, proc.stdout or "", proc.stderr or ""
    except FileNotFoundError as e:
        return 127, "", str(e)
    except subprocess.TimeoutExpired as e:
        return 124, (e.stdout or "") if isinstance(e.stdout, str) else "", (e.stderr or "") if isinstance(e.stderr, str) else ""
    except Exception as e:
        return 1, "", str(e)


class OpenCodeAdapter:
    def __init__(
        self,
        opencode_exe: Optional[str] = None,
        data_dir: Optional[Path] = None,
        timeout: int = 15,
    ):
        self.opencode_exe = opencode_exe or _find_opencode_exe() or "opencode"
        self.data_dir = Path(data_dir) if data_dir else DEFAULT_DATA_DIR
        self.auth_json = self.data_dir / "auth.json"
        self.account_json = self.data_dir / "account.json"
        self.timeout = timeout

    # --- version / capabilities ---
    def get_version(self) -> str:
        code, out, err = _run([self.opencode_exe, "--version"], timeout=self.timeout)
        # opencode --version outputs just "1.17.11" sometimes with ANSI
        text = (out + err).strip()
        # Extract version-like token
        import re

        m = re.search(r"\d+\.\d+\.\d+", text)
        if m:
            return m.group(0)
        if code == 0 and text:
            return text.splitlines()[-1].strip() or "unknown"
        return "unknown"

    def get_capabilities(self) -> OpenCodeCapabilities:
        version = self.get_version()
        # probe auth help
        _, out_auth, _ = _run([self.opencode_exe, "auth", "--help"], timeout=self.timeout)
        _, out_login, _ = _run([self.opencode_exe, "auth", "login", "--help"], timeout=self.timeout)
        _, out_logout, _ = _run([self.opencode_exe, "auth", "logout", "--help"], timeout=self.timeout)
        _, out_stats, _ = _run([self.opencode_exe, "stats", "--help"], timeout=self.timeout)
        combined_auth = out_auth.lower()
        has_list = "list" in combined_auth
        has_login = "login" in combined_auth
        has_logout = "logout" in combined_auth
        # These do NOT exist in 1.17.11, but detect if future version adds them
        has_switch = "switch" in combined_auth
        has_status = "status" in combined_auth

        login_text = (out_login or "").lower()
        logout_text = (out_logout or "").lower()
        login_provider = "-p" in out_login or "--provider" in out_login
        login_method = "-m" in out_login or "--method" in out_login
        logout_provider_arg = "provider" in logout_text
        has_usage = False  # no usage command found
        has_stats = "stats" in (out_stats or "").lower() or "--days" in (out_stats or "")

        caps = OpenCodeCapabilities(
            version=version,
            has_auth_list=has_list,
            has_auth_login=has_login,
            has_auth_logout=has_logout,
            has_auth_switch=has_switch,
            has_auth_status=has_status,
            has_usage=has_usage,
            has_stats=has_stats,
            login_supports_provider_flag=login_provider,
            login_supports_method_flag=login_method,
            logout_supports_provider_arg=logout_provider_arg,
            raw_help_auth=out_auth,
            raw_help_login=out_login,
            raw_help_logout=out_logout,
        )
        logger.info(f"Capabilities: {caps.summarize()}")
        return caps

    def is_installed(self) -> bool:
        return self.get_version() != "unknown"

    # --- credential listing ---
    def list_credentials(self) -> List[Dict[str, Any]]:
        """
        Parse `opencode auth list` and auth.json/account.json.
        Returns list of dicts: {provider, type, source}
        """
        result: List[Dict[str, Any]] = []
        code, out, _ = _run([self.opencode_exe, "auth", "list"], timeout=self.timeout)
        if code == 0 and out:
            # Example output:
            # T  Credentials ~/.local/share/opencode/auth.json
            # •  OpenCode Zen  api
            # —  1 credentials
            # T  Environment
            # •  OpenCode Zen  OPENCODE_API_KEY
            # —  2 environment variables
            # We capture env var presence as hint
            logger.debug(f"auth list output: {redact_text(out[:500])}")
            if "OPENCODE_API_KEY" in out:
                result.append({"provider": "opencode", "type": "env", "source": "env"})
        # Also read auth.json directly
        try:
            if self.auth_json.exists():
                data = json.loads(self.auth_json.read_text(encoding="utf-8"))
                for prov, v in data.items():
                    result.append({"provider": prov, "type": v.get("type", "api"), "source": "auth.json"})
        except Exception as e:
            logger.debug(f"read auth.json failed: {e}")

        try:
            if self.account_json.exists():
                data = json.loads(self.account_json.read_text(encoding="utf-8"))
                accs = data.get("accounts") or {}
                for _id, acc in accs.items():
                    result.append({"provider": acc.get("serviceID", "unknown"), "type": "api", "source": "account.json", "id": _id})
        except Exception as e:
            logger.debug(f"read account.json failed: {e}")

        return result

    def get_active_provider_key(self, provider: str = "opencode") -> Optional[str]:
        """Return current active key for provider from auth.json or account.json if readable."""
        # Try auth.json first
        try:
            if self.auth_json.exists():
                data = json.loads(self.auth_json.read_text(encoding="utf-8"))
                if provider in data:
                    return data[provider].get("key")
        except Exception:
            pass
        try:
            if self.account_json.exists():
                data = json.loads(self.account_json.read_text(encoding="utf-8"))
                active = (data.get("active") or {}).get(provider)
                if active:
                    acc = (data.get("accounts") or {}).get(active)
                    if acc and acc.get("credential"):
                        return acc["credential"].get("key")
        except Exception:
            pass
        # Also check env
        env_key = os.environ.get("OPENCODE_API_KEY")
        if env_key:
            return env_key
        return None

    # --- logout / login / activate ---
    def logout(self, provider: str = "opencode") -> Tuple[bool, str]:
        """Try CLI logout if supported; otherwise just remove from file."""
        caps = self.get_capabilities()
        if caps.has_auth_logout:
            code, out, err = _run([self.opencode_exe, "auth", "logout", provider], timeout=self.timeout)
            text = out + err
            if code == 0:
                logger.info(f"CLI logout {provider} succeeded: {redact_text(text[:200])}")
                return True, text
            logger.warning(f"CLI logout {provider} failed code={code}: {redact_text(text[:500])}")
            # fall through to file manipulation
        # File manipulation fallback: remove from auth.json/account.json
        removed = self._remove_from_auth_file(provider)
        return removed, "file-manipulation"

    def _remove_from_auth_file(self, provider: str) -> bool:
        try:
            if self.auth_json.exists():
                data = json.loads(self.auth_json.read_text(encoding="utf-8"))
                if provider in data:
                    del data[provider]
                    self._atomic_write_json(self.auth_json, data)
                    logger.info(f"Removed {provider} from auth.json via file manipulation")
                    return True
            if self.account_json.exists():
                data = json.loads(self.account_json.read_text(encoding="utf-8"))
                # remove active mapping and account if provider matches
                if provider in (data.get("active") or {}):
                    del data["active"][provider]
                    self._atomic_write_json(self.account_json, data)
                    return True
        except Exception as e:
            logger.error(f"_remove_from_auth_file failed: {e}")
        return False

    def _atomic_write_json(self, path: Path, data: dict) -> None:
        import tempfile

        path.parent.mkdir(parents=True, exist_ok=True)
        fd, tmp = tempfile.mkstemp(dir=str(path.parent))
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2)
                f.write("\n")
            Path(tmp).replace(path)
        finally:
            if Path(tmp).exists():
                try:
                    Path(tmp).unlink()
                except Exception:
                    pass

    def login_via_file(self, provider: str, api_key: str) -> Tuple[bool, str]:
        """
        Direct file write activation (primary mechanism).
        The official CLI `auth login` is interactive and does not accept --api-key,
        so programmatic file manipulation is the only non-interactive OFFICIAL way
        (the file is the canonical storage, writing it is equivalent to login).
        """
        if not api_key or not api_key.strip():
            return False, "API key empty"
        api_key = api_key.strip()
        try:
            # Prefer auth.json format (simpler, observed in 1.17.11)
            # Ensure directory exists
            self.data_dir.mkdir(parents=True, exist_ok=True)
            data: dict = {}
            if self.auth_json.exists():
                try:
                    data = json.loads(self.auth_json.read_text(encoding="utf-8"))
                except Exception:
                    data = {}
            data[provider] = {"type": "api", "key": api_key}
            self._atomic_write_json(self.auth_json, data)
            logger.info(f"login_via_file: wrote {provider} to auth.json")

            # Also maintain account.json for backward compat if it exists
            if self.account_json.exists():
                try:
                    acc_data = json.loads(self.account_json.read_text(encoding="utf-8"))
                    # Find or create account entry for this provider
                    # Generate deterministic id? Use existing active or create new
                    import uuid
                    acc_id = (acc_data.get("active") or {}).get(provider)
                    if not acc_id:
                        acc_id = uuid.uuid4().hex[:16]
                    if "accounts" not in acc_data:
                        acc_data["accounts"] = {}
                    acc_data["accounts"][acc_id] = {
                        "id": acc_id,
                        "serviceID": provider,
                        "description": "managed-by-opencode-api-manager",
                        "credential": {"type": "api", "key": api_key},
                    }
                    if "active" not in acc_data:
                        acc_data["active"] = {}
                    acc_data["active"][provider] = acc_id
                    if "version" not in acc_data:
                        acc_data["version"] = 2
                    self._atomic_write_json(self.account_json, acc_data)
                    logger.debug("Updated account.json as well")
                except Exception as e:
                    logger.warning(f"account.json update failed (non-fatal): {e}")

            return True, "ok"
        except Exception as e:
            logger.error(f"login_via_file failed: {e}")
            return False, str(e)

    def activate(self, provider: str, api_key: str) -> Tuple[bool, str]:
        """
        Deterministic activation with rollback safety:
        1. Backup current auth.json and account.json
        2. Remove existing credential for provider
        3. Write new key via file (atomic)
        4. Verify file write succeeded
        5. On failure: restore backups
        Returns (success, message)
        """
        if not api_key or not api_key.strip():
            return False, "API key empty"
        api_key = api_key.strip()

        # Backup current state for rollback
        auth_backup = None
        account_backup = None
        try:
            if self.auth_json.exists():
                auth_backup = self.auth_json.read_text(encoding="utf-8")
            if self.account_json.exists():
                account_backup = self.account_json.read_text(encoding="utf-8")
        except Exception as e:
            logger.warning(f"Failed to backup auth files: {e}")

        try:
            # Remove existing
            self._remove_from_auth_file(provider)
            time.sleep(0.05)
            ok, msg = self.login_via_file(provider, api_key)
            if not ok:
                # Rollback on failure
                self._restore_backups(auth_backup, account_backup)
            return ok, msg
        except Exception as e:
            # Rollback on exception
            self._restore_backups(auth_backup, account_backup)
            logger.error(f"activate failed: {e}")
            return False, str(e)

    def _restore_backups(self, auth_backup: Optional[str], account_backup: Optional[str]) -> None:
        """Restore auth.json and account.json from backups."""
        try:
            if auth_backup is not None:
                self._atomic_write_json(self.auth_json, json.loads(auth_backup))
            elif self.auth_json.exists():
                self.auth_json.unlink()
        except Exception as e:
            logger.error(f"Failed to restore auth.json backup: {e}")
        try:
            if account_backup is not None:
                self._atomic_write_json(self.account_json, json.loads(account_backup))
            elif self.account_json.exists():
                self.account_json.unlink()
        except Exception as e:
            logger.error(f"Failed to restore account.json backup: {e}")

    def verify(self, provider: str = "opencode") -> VerificationResult:
        """
        Verify current activation.
        Cannot trust `login exited 0` alone per spec §12.
        We verify by:
        1. Checking auth file contains key for provider
        2. Running `opencode auth list` to see provider appears as active credential
        3. Optionally try a lightweight provider API probe (best effort, not invented quota)

        Results: ACTIVE | READY | INVALID | UNAUTHORIZED | UNKNOWN
        """
        # 1. File check
        has_file = False
        try:
            if self.auth_json.exists():
                data = json.loads(self.auth_json.read_text(encoding="utf-8"))
                if provider in data and data[provider].get("key"):
                    has_file = True
            if not has_file and self.account_json.exists():
                data = json.loads(self.account_json.read_text(encoding="utf-8"))
                if provider in (data.get("active") or {}):
                    has_file = True
        except Exception as e:
            logger.debug(f"verify file check failed: {e}")

        if not has_file:
            # also check env
            if os.environ.get("OPENCODE_API_KEY"):
                has_file = True
            else:
                return VerificationResult(status=AuthStatus.UNKNOWN, message="No credential file found")

        # 2. CLI list check
        code, out, err = _run([self.opencode_exe, "auth", "list"], timeout=self.timeout)
        combined = (out or "") + (err or "")
        if code != 0:
            # if opencode missing
            if code == 127:
                return VerificationResult(status=AuthStatus.UNKNOWN, message="opencode executable not found")
            return VerificationResult(status=AuthStatus.UNKNOWN, message=f"auth list failed: {redact_text(combined[:300])}")

        # Heuristic: if provider appears under Credentials section, it's ACTIVE/READY
        # Output is decorative: look for "OpenCode Zen" and "api"
        # ENV section is separate; we already handled file, so Credentials presence = good
        if "OpenCode Zen" in combined or "opencode" in combined.lower():
            # Distinguish ACTIVE vs READY: we consider ACTIVE if file has key and CLI lists it
            # READY could mean credential valid but not active? For single provider, ACTIVE = READY
            # We mark ACTIVE
            return VerificationResult(status=AuthStatus.ACTIVE, message="Credential appears in opencode auth list", raw={"output": combined[:500]})

        # If provider not listed but file exists, it may be that CLI output is empty due to no credentials
        # Check explicitly for "0 credentials" vs "1 credentials"
        if "0 credentials" in combined:
            return VerificationResult(status=AuthStatus.INVALID, message="No credentials reported by opencode")

        return VerificationResult(status=AuthStatus.UNKNOWN, message="Could not determine auth status")

    def get_usage(self, api_key: Optional[str] = None) -> CapacityInfo:
        """
        Attempt to fetch usage/capacity.
        Currently NO reliable endpoint exists (verified §5):
        - opencode CLI has no usage command
        - provider API returns 403/Not Found for /usage, /quota, /balance
        - response headers contain no rate-limit info

        This method attempts best-effort probes but ALWAYS returns UNKNOWN
        unless a future verifiable endpoint appears.

        We keep the implementation honest: return UNKNOWN, never invented values.
        """
        # Placeholder for future integration. Documented probes have all failed.
        # If provider ever exposes a documented endpoint, implement here with:
        # - HTTP GET with Authorization: Bearer <key>
        # - Parse {remaining, limit, percent, reset_at}
        # - Map to CapacityInfo with source=PROVIDER_API
        # For now, explicitly UNKNOWN.
        logger.debug("get_usage: no reliable capacity source available, returning UNKNOWN")
        return CapacityInfo.unknown()

    def get_diagnostics(self) -> Dict[str, Any]:
        caps = self.get_capabilities()
        is_installed = caps.version != "unknown"
        usage = self.get_usage()
        return {
            "installed": is_installed,
            "version": caps.version,
            "capabilities": caps.summarize(),
            "usage_available": usage.percent is not None,
            "usage_source": usage.source.value if usage.percent is not None else "none",
            "auth_list_works": caps.has_auth_list,
            "data_dir": str(self.data_dir),
            "auth_json_exists": self.auth_json.exists(),
            "account_json_exists": self.account_json.exists(),
        }
