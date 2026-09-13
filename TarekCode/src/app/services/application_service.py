"""
Application Service — orchestrates Switch + Failover per spec §10, §12, §13.

Layers:
  UI -> ApplicationService -> SelectionEngine -> OpenCodeAdapter -> OpenCode CLI

Concurrency guard (§27) and single instance (§28) are handled at UI layer,
but service also protects against overlapping switches via threading.Lock.

No secrets are logged; all logs redact keys.
"""
from __future__ import annotations

import logging
import threading
import time
from dataclasses import dataclass
from datetime import datetime, timezone, timedelta
from enum import Enum
from typing import List, Optional, Tuple, Callable

from app.domain.models import CredentialMeta, AuthStatus, CapacityInfo, VerificationResult
from app.infrastructure.secure_store import CredentialStore
from app.infrastructure.metadata_store import MetadataStore
from app.opencode.adapter import OpenCodeAdapter
from app.selection.engine import SelectionEngine
from app.utils.redaction import redact_text
from app.utils.time import utcnow, parse_iso

logger = logging.getLogger(__name__)


class FailureCategory(Enum):
    """Classification of failures for failover decisions."""
    CREDENTIAL = "credential"        # invalid, unauthorized -> failover
    QUOTA = "quota"                   # rate limited, exhausted -> cooldown + failover
    NETWORK = "network"               # timeout, DNS, connection -> NO failover
    OPENCODE_MISSING = "opencode_missing"  # executable not found -> NO failover
    INTERNAL = "internal"             # app error -> NO failover
    UNKNOWN = "unknown"               # ambiguous -> conservative: NO failover


@dataclass
class FailureAnalysis:
    category: FailureCategory
    is_eligible_for_failover: bool
    should_cooldown: bool
    cooldown_minutes: int
    message: str


def _analyze_failure(status: AuthStatus, err_msg: str = "", exception: Optional[Exception] = None) -> FailureAnalysis:
    """
    Classify failure for failover decision per spec §13.

    Eligible for failover:
      - CREDENTIAL: INVALID, UNAUTHORIZED
      - QUOTA: RATE_LIMITED, confirmed quota/exhausted

    NOT eligible:
      - NETWORK: timeout, DNS, connection refused
      - OPENCODE_MISSING: executable not found
      - INTERNAL: app bugs, unexpected exceptions
      - UNKNOWN: ambiguous verification status
    """
    err_lower = (err_msg or "").lower()
    exc_str = str(exception).lower() if exception else ""

    combined = f"{err_lower} {exc_str}"

    # Credential failures
    if status in (AuthStatus.INVALID, AuthStatus.UNAUTHORIZED):
        return FailureAnalysis(
            category=FailureCategory.CREDENTIAL,
            is_eligible_for_failover=True,
            should_cooldown=False,
            cooldown_minutes=0,
            message=f"Credential failure: {err_msg}",
        )

    # Rate limit / quota
    if status == AuthStatus.RATE_LIMITED:
        return FailureAnalysis(
            category=FailureCategory.QUOTA,
            is_eligible_for_failover=True,
            should_cooldown=True,
            cooldown_minutes=30,
            message=f"Rate limited: {err_msg}",
        )

    # Check textual hints for quota
    quota_hints = ["quota", "exhausted", "rate limit", "rate_limit", "capacity", "limit exceeded"]
    if status == AuthStatus.FAILED and any(h in combined for h in quota_hints):
        return FailureAnalysis(
            category=FailureCategory.QUOTA,
            is_eligible_for_failover=True,
            should_cooldown=True,
            cooldown_minutes=30,
            message=f"Quota failure: {err_msg}",
        )

    # Network failures
    network_hints = ["timeout", "dns", "connection refused", "connection reset", "network unreachable", "unreachable"]
    if any(h in combined for h in network_hints):
        return FailureAnalysis(
            category=FailureCategory.NETWORK,
            is_eligible_for_failover=False,
            should_cooldown=False,
            cooldown_minutes=0,
            message=f"Network error: {err_msg}",
        )

    # OpenCode missing
    opencode_hints = ["executable not found", "not found", "file not found", "no such file"]
    if any(h in combined for h in opencode_hints):
        return FailureAnalysis(
            category=FailureCategory.OPENCODE_MISSING,
            is_eligible_for_failover=False,
            should_cooldown=False,
            cooldown_minutes=0,
            message=f"OpenCode missing: {err_msg}",
        )

    # Internal / app errors
    internal_hints = ["traceback", "exception", "internal error", "assertion", "keyerror", "attributeerror", "typeerror", "valueerror"]
    if any(h in combined for h in internal_hints):
        return FailureAnalysis(
            category=FailureCategory.INTERNAL,
            is_eligible_for_failover=False,
            should_cooldown=False,
            cooldown_minutes=0,
            message=f"Internal error: {err_msg}",
        )

    # UNKNOWN verification status - conservative
    if status == AuthStatus.UNKNOWN:
        return FailureAnalysis(
            category=FailureCategory.UNKNOWN,
            is_eligible_for_failover=False,
            should_cooldown=False,
            cooldown_minutes=0,
            message=f"Unknown verification status: {err_msg}",
        )

    # Default: conservative
    return FailureAnalysis(
        category=FailureCategory.UNKNOWN,
        is_eligible_for_failover=False,
        should_cooldown=False,
        cooldown_minutes=0,
        message=f"Unclassified failure: {err_msg}",
    )


class SwitchResult:
    def __init__(
        self,
        success: bool,
        activated: Optional[CredentialMeta],
        attempts: int,
        tried_ids: List[str],
        message: str,
        verification: Optional[VerificationResult] = None,
    ):
        self.success = success
        self.activated = activated
        self.attempts = attempts
        self.tried_ids = tried_ids
        self.message = message
        self.verification = verification

    def to_dict(self):
        return {
            "success": self.success,
            "activated": self.activated.to_dict() if self.activated else None,
            "attempts": self.attempts,
            "tried_ids": self.tried_ids,
            "message": self.message,
            "verification": self.verification.status.value if self.verification else None,
        }


class ApplicationService:
    def __init__(
        self,
        metadata_store: Optional[MetadataStore] = None,
        credential_store: Optional[CredentialStore] = None,
        opencode_adapter: Optional[OpenCodeAdapter] = None,
        selection_engine: Optional[SelectionEngine] = None,
        max_attempts: int = 3,
        cooldown_minutes: int = 30,
    ):
        self.metadata_store = metadata_store or MetadataStore()
        self.credential_store = credential_store or CredentialStore()
        self.adapter = opencode_adapter or OpenCodeAdapter()
        self.selection = selection_engine or SelectionEngine()
        self.max_attempts = max_attempts
        self.cooldown_minutes = cooldown_minutes
        self._switch_lock = threading.Lock()
        self._progress_callback: Optional[Callable[[str], None]] = None

    def set_progress_callback(self, cb: Callable[[str], None]) -> None:
        self._progress_callback = cb

    def _emit(self, msg: str) -> None:
        logger.info(msg)
        if self._progress_callback:
            try:
                self._progress_callback(msg)
            except Exception:
                pass

    # --- CRUD ---
    def add_api(self, name: str, provider: str, api_key: str) -> CredentialMeta:
        if not name or not name.strip():
            raise ValueError("Name is required")
        if not api_key or not api_key.strip():
            raise ValueError("API key is required")
        name = name.strip()
        provider = (provider or "opencode").strip() or "opencode"
        api_key = api_key.strip()

        # Validate uniqueness of name (case-insensitive)
        existing = self.metadata_store.list()
        if any(m.name.lower() == name.lower() for m in existing):
            raise ValueError(f"An API named '{name}' already exists")

        meta = CredentialMeta(name=name, provider=provider, auth_status=AuthStatus.UNKNOWN)
        # Save secret securely first
        self.credential_store.save(meta.id, api_key)
        # Probe verification immediately (best-effort)
        try:
            # Temporarily try to verify key by attempting a lightweight check?
            # We don't auto-activate on add; just check if key looks valid format and try adapter get_usage
            cap = self.adapter.get_usage(api_key)
            meta.capacity = cap
            meta.last_checked_at = utcnow().isoformat()
            # No automatic auth_status change until Switch; keep UNKNOWN or READY if format ok
            if api_key.startswith("sk-") and len(api_key) > 20:
                meta.auth_status = AuthStatus.READY
            else:
                meta.auth_status = AuthStatus.UNKNOWN
        except Exception as e:
            logger.debug(f"Initial capacity probe failed: {e}")
            meta.auth_status = AuthStatus.UNKNOWN

        self.metadata_store.upsert(meta)
        self._emit(f"Added API '{name}' provider={provider}")
        return meta

    def remove_api(self, cred_id: str) -> None:
        meta = self.metadata_store.get(cred_id)
        if not meta:
            raise ValueError("Credential not found")
        # Remove secret
        try:
            self.credential_store.delete(cred_id)
        except Exception as e:
            logger.warning(f"delete secret failed for {cred_id}: {e}")
        # Remove metadata
        self.metadata_store.delete(cred_id)
        # If active id was this, clear it
        if self.metadata_store.get_active_id() == cred_id:
            self.metadata_store.set_active_id(None)
        self._emit(f"Removed API '{meta.name}'")

    def list_apis(self) -> List[CredentialMeta]:
        return self.metadata_store.list()

    def get_active(self) -> Optional[CredentialMeta]:
        active_id = self.metadata_store.get_active_id()
        if not active_id:
            return None
        return self.metadata_store.get(active_id)

    # --- Switch ---
    def switch(self, provider_filter: Optional[str] = None) -> SwitchResult:
        """
        Full switch flow:

        Switch
          ↓
        Select best API (ranked)
          ↓
        Activate credential (logout/login via adapter)
          ↓
        Verify OpenCode state
          ↓
        Success -> update last_used_at, set active, DONE
        Failure -> classify, apply cooldown/failure_count, try next candidate (max_attempts)

        Thread-safe via _switch_lock (§27).
        """
        if not self._switch_lock.acquire(blocking=False):
            return SwitchResult(
                success=False,
                activated=None,
                attempts=0,
                tried_ids=[],
                message="Switch already in progress",
            )

        try:
            candidates = self.metadata_store.list()
            if provider_filter:
                candidates = [c for c in candidates if c.provider == provider_filter]

            if not candidates:
                return SwitchResult(
                    success=False,
                    activated=None,
                    attempts=0,
                    tried_ids=[],
                    message="No APIs configured. Please add an API first.",
                )

            # Check opencode installed
            if not self.adapter.is_installed():
                return SwitchResult(
                    success=False,
                    activated=None,
                    attempts=0,
                    tried_ids=[],
                    message="OpenCode executable not found. Please ensure OpenCode is installed and on PATH.",
                )

            ranked = self.selection.rank(candidates)
            if not ranked:
                return SwitchResult(
                    success=False,
                    activated=None,
                    attempts=0,
                    tried_ids=[],
                    message="No eligible APIs (all invalid or in cooldown).",
                )

            self._emit(f"Ranked {len(ranked)} candidates: {[m.name for m in ranked]}")

            tried_ids: List[str] = []
            last_error = ""
            attempts = 0

            for meta in ranked:
                if attempts >= self.max_attempts:
                    break
                if meta.id in tried_ids:
                    continue
                # Double-check cooldown/invalid at selection time (in case metadata changed)
                if meta.is_invalid():
                    self._emit(f"Skipping {meta.name}: invalid ({meta.auth_status})")
                    continue
                if meta.is_in_cooldown():
                    self._emit(f"Skipping {meta.name}: in cooldown until {meta.cooldown_until}")
                    continue

                tried_ids.append(meta.id)
                attempts += 1
                self._emit(f"Attempt {attempts}: activating {meta.name} ({meta.provider})")

                secret = self.credential_store.get(meta.id)
                if not secret:
                    # Mark invalid
                    meta.failure_count += 1
                    meta.last_failure = utcnow().isoformat()
                    meta.auth_status = AuthStatus.INVALID
                    self.metadata_store.upsert(meta)
                    last_error = "Secret not found in secure store"
                    self._emit(f"Failed {meta.name}: {last_error}")
                    analysis = _analyze_failure(AuthStatus.INVALID, last_error)
                    if analysis.is_eligible_for_failover:
                        continue
                    else:
                        break

                # Activate
                try:
                    ok, msg = self.adapter.activate(provider=meta.provider, api_key=secret)
                    if not ok:
                        # activation failed at file level
                        meta.failure_count += 1
                        meta.last_failure = redact_text(msg)[:500]
                        meta.auth_status = AuthStatus.FAILED
                        analysis = _analyze_failure(AuthStatus.FAILED, msg)
                        if analysis.should_cooldown:
                            meta.cooldown_until = (utcnow() + timedelta(minutes=analysis.cooldown_minutes)).isoformat()
                            meta.auth_status = AuthStatus.RATE_LIMITED
                        self.metadata_store.upsert(meta)
                        last_error = msg
                        self._emit(f"Activation failed {meta.name}: {redact_text(msg[:200])}")
                        if analysis.is_eligible_for_failover:
                            continue
                        else:
                            break
                    # Verify (MANDATORY per §12)
                    verification = self.adapter.verify(provider=meta.provider)
                    logger.info(f"Verification for {meta.name}: {verification.status} {redact_text(verification.message[:200])}")

                    if verification.status in (AuthStatus.ACTIVE, AuthStatus.READY):
                        # SUCCESS
                        meta.last_used_at = utcnow().isoformat()
                        meta.last_checked_at = utcnow().isoformat()
                        meta.auth_status = AuthStatus.ACTIVE
                        meta.failure_count = 0
                        meta.last_failure = None
                        meta.cooldown_until = None
                        # Update capacity timestamp if known? Keep UNKNOWN as is
                        self.metadata_store.upsert(meta)
                        self.metadata_store.set_active_id(meta.id)
                        # Mark previous active as READY (not ACTIVE)
                        for other in self.metadata_store.list():
                            if other.id != meta.id and other.auth_status == AuthStatus.ACTIVE:
                                other.auth_status = AuthStatus.READY
                                self.metadata_store.upsert(other)

                        self._emit(f"Switch successful: {meta.name} verified as {verification.status}")
                        return SwitchResult(
                            success=True,
                            activated=meta,
                            attempts=attempts,
                            tried_ids=tried_ids,
                            message=f"Switched to {meta.name}",
                            verification=verification,
                        )
                    else:
                        # Verification failed
                        failed_status = verification.status
                        meta.failure_count += 1
                        meta.last_failure = utcnow().isoformat()
                        meta.last_checked_at = utcnow().isoformat()
                        analysis = _analyze_failure(failed_status, verification.message)
                        # Map verification status to auth_status
                        if failed_status in (AuthStatus.INVALID, AuthStatus.UNAUTHORIZED):
                            meta.auth_status = failed_status
                        elif failed_status == AuthStatus.RATE_LIMITED:
                            meta.auth_status = AuthStatus.RATE_LIMITED
                            meta.cooldown_until = (utcnow() + timedelta(minutes=analysis.cooldown_minutes)).isoformat()
                        else:
                            meta.auth_status = AuthStatus.FAILED if failed_status == AuthStatus.UNKNOWN else failed_status

                        if analysis.should_cooldown and meta.auth_status != AuthStatus.RATE_LIMITED:
                            meta.cooldown_until = (utcnow() + timedelta(minutes=analysis.cooldown_minutes)).isoformat()

                        self.metadata_store.upsert(meta)
                        last_error = verification.message
                        self._emit(f"Verification failed for {meta.name}: {failed_status} - {redact_text(last_error[:200])}")

                        if analysis.is_eligible_for_failover:
                            self._emit(f"Eligible for failover, trying next candidate")
                            continue
                        else:
                            # Non-eligible failure: do not try next (network, exe missing, etc.)
                            break

                except Exception as e:
                    msg = str(e)
                    logger.exception(f"Exception during activation of {meta.name}: {redact_text(msg)}")
                    meta.failure_count += 1
                    meta.last_failure = utcnow().isoformat()
                    meta.auth_status = AuthStatus.FAILED
                    self.metadata_store.upsert(meta)
                    last_error = msg
                    # Don't failover on internal errors
                    break

            # All attempts exhausted
            return SwitchResult(
                success=False,
                activated=None,
                attempts=attempts,
                tried_ids=tried_ids,
                message=f"Switch failed after {attempts} attempt(s). Last: {redact_text(last_error[:300])}" if last_error else "Switch failed: no eligible candidates",
            )

        finally:
            self._switch_lock.release()

    def refresh_capacity_if_needed(self, meta: CredentialMeta) -> CredentialMeta:
        """
        Check TTL and refresh if stale. Currently always UNKNOWN, but structure in place.
        """
        if self.selection.should_refresh_capacity(meta):
            try:
                secret = self.credential_store.get(meta.id)
                cap = self.adapter.get_usage(secret) if secret else CapacityInfo.unknown()
                meta.capacity = cap
                meta.last_checked_at = utcnow().isoformat()
                self.metadata_store.upsert(meta)
            except Exception as e:
                logger.debug(f"refresh capacity failed for {meta.name}: {e}")
        return meta

    def refresh_all_stale(self, background: bool = False) -> None:
        """
        Background refresh for UI. Non-blocking if background=True.
        """
        def _do():
            for meta in self.metadata_store.list():
                self.refresh_capacity_if_needed(meta)

        if background:
            t = threading.Thread(target=_do, daemon=True, name="capacity-refresh")
            t.start()
        else:
            _do()

    def get_diagnostics(self) -> dict:
        return self.adapter.get_diagnostics()
