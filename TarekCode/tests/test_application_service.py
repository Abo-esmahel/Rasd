"""
Tests for ApplicationService logic with fakes.
"""
import tempfile
from pathlib import Path
from unittest.mock import MagicMock, patch
from datetime import datetime, timezone, timedelta

import pytest

from app.domain.models import CredentialMeta, CapacityInfo, CapacitySource, AuthStatus, VerificationResult
from app.infrastructure.metadata_store import MetadataStore
from app.infrastructure.secure_store import CredentialStore
from app.opencode.adapter import OpenCodeAdapter
from app.selection.engine import SelectionEngine
from app.services.application_service import ApplicationService

def make_service(tmp_path):
    base = Path(tempfile.mkdtemp())
    meta_store = MetadataStore(base_dir=base)
    dpapi_dir = Path(tempfile.mkdtemp())
    cred_store = CredentialStore(dpapi_dir=dpapi_dir)

    # Mock adapter that doesn't need real opencode
    adapter = MagicMock(spec=OpenCodeAdapter)
    adapter.is_installed.return_value = True
    adapter.get_usage.return_value = CapacityInfo.unknown()
    adapter.get_capabilities.return_value = MagicMock(version="1.17.11")
    adapter.get_diagnostics.return_value = {"installed": True, "version": "1.17.11"}

    service = ApplicationService(
        metadata_store=meta_store,
        credential_store=cred_store,
        opencode_adapter=adapter,
        selection_engine=SelectionEngine(),
        max_attempts=3,
    )
    return service, adapter, meta_store, cred_store

def test_add_and_remove_api():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    meta = service.add_api(name="Personal 01", provider="opencode", api_key="sk-fake1234567890123456")
    assert meta.name == "Personal 01"
    apis = service.list_apis()
    assert len(apis) == 1

    # duplicate name should fail
    with pytest.raises(ValueError):
        service.add_api(name="Personal 01", provider="opencode", api_key="sk-other")

    service.remove_api(meta.id)
    assert len(service.list_apis()) == 0

def test_switch_no_apis():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    result = service.switch()
    assert not result.success
    assert "No APIs" in result.message

def test_switch_success():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    m1 = service.add_api(name="P1", provider="opencode", api_key="sk-fake1-123456789012345678")
    # ensure last_used makes P1 older than nothing, but only one
    adapter.activate.return_value = (True, "ok")
    adapter.verify.return_value = VerificationResult(status=AuthStatus.ACTIVE, message="ok")

    result = service.switch()
    assert result.success
    assert result.activated.name == "P1"
    assert service.metadata_store.get_active_id() == m1.id

def test_switch_failover_to_next():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    # Use recent times to ensure ranking is predictable: both UNKNOWN -> LRU picks older first
    import time
    m1 = service.add_api(name="P1", provider="opencode", api_key="sk-fake1-1234567890123456")
    # make P1 older than P2? Actually first added is older. Let's manipulate timestamps to make P1 oldest
    from app.utils.time import utcnow
    # set P1 last_used to yesterday, P2 to now+1h? Wait now is after. Set P1 older.
    m1.last_used_at = (datetime.now(timezone.utc) - timedelta(days=1)).isoformat()
    service.metadata_store.upsert(m1)
    m2 = service.add_api(name="P2", provider="opencode", api_key="sk-fake2-1234567890123456")
    m2.last_used_at = datetime.now(timezone.utc).isoformat()
    service.metadata_store.upsert(m2)

    # First candidate fails with INVALID (eligible for failover), second succeeds
    def activate_side(provider, api_key):
        if "fake1" in api_key:
            return True, "ok"  # activation ok but verify will say invalid
        return True, "ok"

    adapter.activate.side_effect = activate_side

    def verify_side(provider="opencode"):
        # Check which key is currently "active" by looking at call count? Instead use history
        # We can detect last activate's key
        calls = adapter.activate.call_args_list
        if calls and "fake1" in str(calls[-1]):
            return VerificationResult(status=AuthStatus.INVALID, message="invalid credential")
        return VerificationResult(status=AuthStatus.ACTIVE, message="ok")

    adapter.verify.side_effect = verify_side

    result = service.switch()
    assert result.success
    assert result.activated.name == "P2"
    assert result.attempts == 2

def test_switch_verification_failure_no_failover_on_unknown():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    service.add_api(name="P1", provider="opencode", api_key="sk-fake1-123456789")
    service.add_api(name="P2", provider="opencode", api_key="sk-fake2-123456789")

    adapter.activate.return_value = (True, "ok")
    adapter.verify.return_value = VerificationResult(status=AuthStatus.UNKNOWN, message="network timeout")

    result = service.switch()
    assert not result.success
    # Should not try all 2, should stop after first because UNKNOWN is non-eligible
    assert result.attempts == 1

def test_switch_opencode_missing():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    service.add_api(name="P1", provider="opencode", api_key="sk-fake1-123456789")
    adapter.is_installed.return_value = False
    result = service.switch()
    assert not result.success
    assert "not found" in result.message.lower()

def test_switch_concurrent_guard():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    service.add_api(name="P1", provider="opencode", api_key="sk-fake1-123456789")
    adapter.activate.return_value = (True, "ok")
    adapter.verify.return_value = VerificationResult(status=AuthStatus.ACTIVE, message="ok")

    # Simulate lock held
    service._switch_lock.acquire()
    try:
        result = service.switch()
        assert not result.success
        assert "already in progress" in result.message.lower()
    finally:
        service._switch_lock.release()

def test_restart_persistence():
    base = Path(tempfile.mkdtemp())
    dpapi_dir = Path(tempfile.mkdtemp())
    meta_store = MetadataStore(base_dir=base)
    cred_store = CredentialStore(dpapi_dir=dpapi_dir)
    adapter = MagicMock(spec=OpenCodeAdapter)
    adapter.is_installed.return_value = True
    adapter.get_usage.return_value = CapacityInfo.unknown()

    s1 = ApplicationService(metadata_store=meta_store, credential_store=cred_store, opencode_adapter=adapter)
    m = s1.add_api(name="Persist", provider="opencode", api_key="sk-persist12345678")

    # new service instance reading same files should see it
    s2 = ApplicationService(metadata_store=MetadataStore(base_dir=base), credential_store=CredentialStore(dpapi_dir=dpapi_dir), opencode_adapter=adapter)
    assert len(s2.list_apis()) == 1
    assert s2.list_apis()[0].name == "Persist"

def test_rate_limit_cooldown():
    service, adapter, _, _ = make_service(Path(tempfile.mkdtemp()))
    m = service.add_api(name="P1", provider="opencode", api_key="sk-rate1234567890")
    adapter.activate.return_value = (True, "ok")
    adapter.verify.return_value = VerificationResult(status=AuthStatus.RATE_LIMITED, message="rate limited")

    result = service.switch()
    assert not result.success
    # After rate limited, meta should have cooldown
    updated = service.metadata_store.get(m.id)
    assert updated.cooldown_until is not None
    assert updated.auth_status == AuthStatus.RATE_LIMITED
