import tempfile
import json
from pathlib import Path
from unittest.mock import patch

from app.opencode.adapter import OpenCodeAdapter
from app.domain.models import CapacityInfo

def test_adapter_version_and_capabilities(tmp_path):
    # Patch subprocess run to simulate opencode responses
    import app.opencode.adapter as mod

    # Simulate opencode --version => 1.17.11
    def fake_run(cmd, timeout=15, env=None, hide_window=True):
        joined = " ".join(cmd)
        if "--version" in joined:
            return (0, "1.17.11\n", "")
        if "auth --help" in joined:
            return (0, "Commands: opencode auth list\n opencode auth login [url]\n opencode auth logout [provider]\n", "")
        if "auth login --help" in joined:
            return (0, "Options: -p, --provider  -m, --method", "")
        if "auth logout --help" in joined:
            return (0, "Positionals: provider", "")
        if "stats --help" in joined:
            return (0, "Options: --days --models", "")
        if "auth list" in joined:
            return (0, "T  Credentials ~/.local/share/opencode/auth.json\n• OpenCode Zen api\n", "")
        return (0, "", "")

    with patch.object(mod, "_run", side_effect=fake_run):
        adapter = OpenCodeAdapter(opencode_exe="opencode", data_dir=Path(tmp_path))
        assert adapter.get_version() == "1.17.11"
        caps = adapter.get_capabilities()
        assert caps.has_auth_list
        assert caps.has_auth_login
        assert caps.has_auth_logout
        assert not caps.has_auth_switch
        assert caps.version == "1.17.11"

def test_adapter_get_usage_always_unknown(tmp_path):
    adapter = OpenCodeAdapter(opencode_exe="opencode", data_dir=Path(tmp_path))
    cap = adapter.get_usage(api_key="sk-fake")
    assert cap.is_unknown()
    assert cap.percent is None

def test_adapter_activate_and_verify(tmp_path):
    adapter = OpenCodeAdapter(opencode_exe="opencode", data_dir=Path(tmp_path))
    # No _run patch for auth list: we want file manipulation only
    import app.opencode.adapter as mod
    def fake_run(cmd, timeout=15, env=None, hide_window=True):
        joined = " ".join(cmd)
        if "auth list" in joined:
            return (0, "T  Credentials\n• OpenCode Zen  api\n— 1 credentials\n", "")
        if "--version" in joined:
            return (0, "1.17.11", "")
        return (0, "", "")
    with patch.object(mod, "_run", side_effect=fake_run):
        ok, msg = adapter.activate(provider="opencode", api_key="sk-test1234567890123")
        assert ok
        # Verify should now find file
        result = adapter.verify(provider="opencode")
        assert result.status.value in ("ACTIVE", "READY")

def test_adapter_missing_exe(tmp_path):
    import app.opencode.adapter as mod
    def fake_run(cmd, timeout=15, env=None, hide_window=True):
        return (127, "", "not found")
    with patch.object(mod, "_run", side_effect=fake_run):
        adapter = OpenCodeAdapter(opencode_exe="nonexistent_opencode", data_dir=Path(tmp_path))
        assert adapter.get_version() == "unknown"
        assert not adapter.is_installed()
