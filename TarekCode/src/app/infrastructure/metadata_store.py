"""
Metadata store: stores credential metadata WITHOUT secrets.
Location: %APPDATA%\\OpenCodeAPIManager\\accounts.json etc.

- accounts.json  : dict of credential metadata
- settings.json  : app settings
- state.json     : transient state (active credential id)

All writes are atomic (write to temp + rename).
"""
from __future__ import annotations

import json
import os
import tempfile
from pathlib import Path
from typing import Dict, List, Optional

from app.domain.models import CredentialMeta

DEFAULT_APPDATA = Path(os.environ.get("APPDATA", str(Path.home()))) / "OpenCodeAPIManager"

class MetadataStore:
    def __init__(self, base_dir: Optional[Path] = None):
        self.base_dir = Path(base_dir) if base_dir else DEFAULT_APPDATA
        self.base_dir.mkdir(parents=True, exist_ok=True)
        self.accounts_path = self.base_dir / "accounts.json"
        self.settings_path = self.base_dir / "settings.json"
        self.state_path = self.base_dir / "state.json"

    # --- atomic write helper ---
    def _atomic_write(self, path: Path, data: dict) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        fd, tmp = tempfile.mkstemp(dir=str(path.parent), suffix=".tmp")
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
                f.write("\n")
            # replace atomically
            Path(tmp).replace(path)
        finally:
            if Path(tmp).exists():
                try:
                    Path(tmp).unlink()
                except Exception:
                    pass

    # --- accounts ---
    def load_all(self) -> Dict[str, CredentialMeta]:
        if not self.accounts_path.exists():
            return {}
        try:
            raw = json.loads(self.accounts_path.read_text(encoding="utf-8"))
            # raw may be {id: meta_dict} or list
            out: Dict[str, CredentialMeta] = {}
            if isinstance(raw, dict):
                # check if dict is mapping id->dict or single meta?
                # heuristic: if values are dicts with "id", treat as map
                for k, v in raw.items():
                    if isinstance(v, dict) and "id" in v:
                        try:
                            m = CredentialMeta.from_dict(v)
                            out[m.id] = m
                        except Exception:
                            continue
                    # else maybe wrapper with "accounts" key
                if "accounts" in raw and isinstance(raw["accounts"], dict):
                    out = {}
                    for k, v in raw["accounts"].items():
                        m = CredentialMeta.from_dict(v)
                        out[m.id] = m
            elif isinstance(raw, list):
                for item in raw:
                    m = CredentialMeta.from_dict(item)
                    out[m.id] = m
            return out
        except Exception as e:
            # corrupted file: backup and return empty
            try:
                backup = self.accounts_path.with_suffix(".bak")
                self.accounts_path.replace(backup)
            except Exception:
                pass
            return {}

    def save_all(self, metas: Dict[str, CredentialMeta]) -> None:
        data = {cid: m.to_dict() for cid, m in metas.items()}
        self._atomic_write(self.accounts_path, data)

    def get(self, cred_id: str) -> Optional[CredentialMeta]:
        all_m = self.load_all()
        return all_m.get(cred_id)

    def upsert(self, meta: CredentialMeta) -> None:
        all_m = self.load_all()
        all_m[meta.id] = meta
        self.save_all(all_m)

    def delete(self, cred_id: str) -> None:
        all_m = self.load_all()
        if cred_id in all_m:
            del all_m[cred_id]
            self.save_all(all_m)

    def list(self) -> List[CredentialMeta]:
        return list(self.load_all().values())

    # --- settings ---
    def load_settings(self) -> dict:
        if not self.settings_path.exists():
            return {"capacity_ttl_seconds": 600, "provider_filter": None}
        try:
            return json.loads(self.settings_path.read_text(encoding="utf-8"))
        except Exception:
            return {}

    def save_settings(self, settings: dict) -> None:
        self._atomic_write(self.settings_path, settings)

    # --- state (active credential) ---
    def load_state(self) -> dict:
        if not self.state_path.exists():
            return {}
        try:
            return json.loads(self.state_path.read_text(encoding="utf-8"))
        except Exception:
            return {}

    def save_state(self, state: dict) -> None:
        self._atomic_write(self.state_path, state)

    def get_active_id(self) -> Optional[str]:
        s = self.load_state()
        return s.get("active_credential_id")

    def set_active_id(self, cred_id: Optional[str]) -> None:
        s = self.load_state()
        s["active_credential_id"] = cred_id
        self.save_state(s)
