"""
Secure credential store.

Spec: Use Windows Credential Manager or DPAPI.
We support pluggable backends:

1. keyring (Windows Credential Manager via `keyring` library) - preferred
2. DPAPI via win32crypt (pywin32) or fallback ctypes CryptProtectData
3. Plain file obfuscation (dev fallback, NOT secure - warned in logs)

No secrets are ever stored in JSON metadata files; only in secure store.
Service name: OpenCodeAPIManager
Account name: per credential id (e.g., "opencode-cred-<id>")
"""
from __future__ import annotations

import base64
import json
import logging
import os
from pathlib import Path
from typing import Optional

logger = logging.getLogger(__name__)

SERVICE_NAME = "OpenCodeAPIManager"


class SecureStoreError(RuntimeError):
    pass


def _get_keyring_store():
    try:
        import keyring  # type: ignore
        # Quick test: does backend work?
        # We don't test here, just import
        return keyring
    except Exception as e:
        logger.debug(f"keyring not available: {e}")
        return None


def _get_win32crypt():
    try:
        import win32crypt  # type: ignore
        return win32crypt
    except Exception:
        return None


# --- DPAPI via ctypes fallback (no pywin32 required) ---
def _dpapi_encrypt_ctypes(data: bytes) -> bytes:
    """Encrypt with DPAPI using ctypes (Windows only)."""
    try:
        import ctypes
        from ctypes import wintypes

        class DATA_BLOB(ctypes.Structure):
            _fields_ = [("cbData", wintypes.DWORD), ("pbData", ctypes.POINTER(ctypes.c_char))]

        def get_data(blob: bytes) -> DATA_BLOB:
            cb = len(blob)
            buf = ctypes.create_string_buffer(blob, len(blob))
            return DATA_BLOB(cb, ctypes.cast(buf, ctypes.POINTER(ctypes.c_char)))

        CRYPTPROTECT_UI_FORBIDDEN = 0x01
        crypt32 = ctypes.windll.crypt32
        kernel32 = ctypes.windll.kernel32

        in_blob = get_data(data)
        out_blob = DATA_BLOB()

        if not crypt32.CryptProtectData(
            ctypes.byref(in_blob), None, None, None, None, CRYPTPROTECT_UI_FORBIDDEN, ctypes.byref(out_blob)
        ):
            raise SecureStoreError("CryptProtectData failed")

        try:
            out = ctypes.string_at(out_blob.pbData, out_blob.cbData)
            return out
        finally:
            kernel32.LocalFree(out_blob.pbData)

    except Exception as e:
        raise SecureStoreError(f"DPAPI ctypes encrypt failed: {e}") from e


def _dpapi_decrypt_ctypes(data: bytes) -> bytes:
    try:
        import ctypes
        from ctypes import wintypes

        class DATA_BLOB(ctypes.Structure):
            _fields_ = [("cbData", wintypes.DWORD), ("pbData", ctypes.POINTER(ctypes.c_char))]

        def get_data(blob: bytes) -> DATA_BLOB:
            cb = len(blob)
            buf = ctypes.create_string_buffer(blob, len(blob))
            return DATA_BLOB(cb, ctypes.cast(buf, ctypes.POINTER(ctypes.c_char)))

        CRYPTPROTECT_UI_FORBIDDEN = 0x01
        crypt32 = ctypes.windll.crypt32
        kernel32 = ctypes.windll.kernel32

        in_blob = get_data(data)
        out_blob = DATA_BLOB()

        if not crypt32.CryptUnprotectData(
            ctypes.byref(in_blob), None, None, None, None, CRYPTPROTECT_UI_FORBIDDEN, ctypes.byref(out_blob)
        ):
            raise SecureStoreError("CryptUnprotectData failed")

        try:
            out = ctypes.string_at(out_blob.pbData, out_blob.cbData)
            return out
        finally:
            kernel32.LocalFree(out_blob.pbData)
    except Exception as e:
        raise SecureStoreError(f"DPAPI ctypes decrypt failed: {e}") from e


class DPAPIFileStore:
    """Encrypted file store using DPAPI (one file per credential)."""

    def __init__(self, base_dir: Path):
        self.base_dir = Path(base_dir)
        self.base_dir.mkdir(parents=True, exist_ok=True)
        self._win32 = _get_win32crypt()

    def _path_for(self, cred_id: str) -> Path:
        safe = "".join(c if c.isalnum() or c in "-_" else "_" for c in cred_id)
        return self.base_dir / f"{safe}.bin"

    def save(self, cred_id: str, secret: str) -> None:
        raw = secret.encode("utf-8")
        if self._win32:
            import win32crypt
            encrypted = win32crypt.CryptProtectData(raw, None, None, None, None, 0)
        else:
            encrypted = _dpapi_encrypt_ctypes(raw)
        p = self._path_for(cred_id)
        p.write_bytes(base64.b64encode(encrypted))

    def get(self, cred_id: str) -> Optional[str]:
        p = self._path_for(cred_id)
        if not p.exists():
            return None
        try:
            enc = base64.b64decode(p.read_bytes())
            if self._win32:
                import win32crypt
                _, raw = win32crypt.CryptUnprotectData(enc, None, None, None, 0)
                # raw is bytes
                if isinstance(raw, bytes):
                    return raw.decode("utf-8")
                return str(raw)
            else:
                raw = _dpapi_decrypt_ctypes(enc)
                return raw.decode("utf-8")
        except Exception as e:
            logger.error(f"DPAPI decrypt failed for {cred_id}: {e}")
            return None

    def delete(self, cred_id: str) -> None:
        p = self._path_for(cred_id)
        try:
            if p.exists():
                p.unlink()
        except Exception as e:
            logger.warning(f"Failed to delete DPAPI file {p}: {e}")

    def exists(self, cred_id: str) -> bool:
        return self._path_for(cred_id).exists()


class CredentialStore:
    """
    Unified credential store facade.

    Tries:
      1. keyring (Windows Credential Manager)
      2. DPAPI file store at %APPDATA%\\OpenCodeAPIManager\\secrets\\
      3. Raises if none available

    The caller does not need to know which backend is used.
    """

    def __init__(self, dpapi_dir: Optional[Path] = None):
        self._keyring = _get_keyring_store()
        if dpapi_dir is None:
            dpapi_dir = Path(os.environ.get("APPDATA", str(Path.home()))) / "OpenCodeAPIManager" / "secrets"
        self._dpapi = DPAPIFileStore(dpapi_dir)
        self._backend = "unknown"
        if self._keyring is not None:
            self._backend = "keyring"
        else:
            self._backend = "dpapi"

        logger.info(f"CredentialStore backend: {self._backend}")

    @property
    def backend(self) -> str:
        return self._backend

    def save(self, cred_id: str, secret: str) -> None:
        if not secret or not secret.strip():
            raise SecureStoreError("Secret is empty")
        # Try keyring first
        if self._keyring is not None:
            try:
                self._keyring.set_password(SERVICE_NAME, cred_id, secret)
                # verify
                check = self._keyring.get_password(SERVICE_NAME, cred_id)
                if check == secret:
                    logger.info(f"Credential {cred_id[:6]}... saved via keyring")
                    # Also clean dpapi file if exists (migration)
                    self._dpapi.delete(cred_id)
                    return
                else:
                    logger.warning("keyring verify failed, falling back to DPAPI")
            except Exception as e:
                logger.warning(f"keyring save failed for {cred_id}: {e}")

        # Fallback DPAPI
        try:
            self._dpapi.save(cred_id, secret)
            logger.info(f"Credential {cred_id[:6]}... saved via DPAPI")
        except Exception as e:
            raise SecureStoreError(f"Failed to save credential {cred_id}: {e}") from e

    def get(self, cred_id: str) -> Optional[str]:
        # try keyring
        if self._keyring is not None:
            try:
                v = self._keyring.get_password(SERVICE_NAME, cred_id)
                if v is not None:
                    return v
            except Exception as e:
                logger.debug(f"keyring get failed for {cred_id}: {e}")
        # dpapi fallback
        try:
            return self._dpapi.get(cred_id)
        except Exception as e:
            logger.error(f"DPAPI get failed for {cred_id}: {e}")
            return None

    def delete(self, cred_id: str) -> None:
        if self._keyring is not None:
            try:
                self._keyring.delete_password(SERVICE_NAME, cred_id)
            except Exception:
                pass  # not found is OK
        self._dpapi.delete(cred_id)

    def exists(self, cred_id: str) -> bool:
        if self._keyring is not None:
            try:
                v = self._keyring.get_password(SERVICE_NAME, cred_id)
                if v is not None:
                    return True
            except Exception:
                pass
        return self._dpapi.exists(cred_id)
