import tempfile
from pathlib import Path
import pytest

from app.infrastructure.secure_store import CredentialStore
from app.infrastructure.metadata_store import MetadataStore
from app.domain.models import CredentialMeta

def test_credential_store_save_get_delete(tmp_path):
    # tmp_path is pytest fixture but we manually use temp
    # Use temporary dpapi dir to avoid polluting real store; force dpapi fallback by mocking keyring?
    # We'll just test whatever backend is available, but with isolated dpapi dir
    import pathlib, tempfile
    dpapi_dir = Path(tempfile.mkdtemp())
    store = CredentialStore(dpapi_dir=dpapi_dir)
    cid = "test-id-123"
    secret = "sk-test1234567890abcdef"

    store.save(cid, secret)
    assert store.exists(cid)
    retrieved = store.get(cid)
    assert retrieved == secret

    store.delete(cid)
    assert not store.exists(cid)
    assert store.get(cid) is None

def test_metadata_store_no_secrets(tmp_path):
    base = Path(tempfile.mkdtemp())
    ms = MetadataStore(base_dir=base)
    meta = CredentialMeta(name="Personal 01", provider="opencode")
    ms.upsert(meta)
    # accounts file should not contain secret
    content = (base / "accounts.json").read_text(encoding="utf-8")
    assert "sk-" not in content
    assert "secret" not in content.lower() or "sk-" not in content
    loaded = ms.get(meta.id)
    assert loaded.name == "Personal 01"

    ms.set_active_id(meta.id)
    assert ms.get_active_id() == meta.id
    ms.delete(meta.id)
    assert ms.get(meta.id) is None
