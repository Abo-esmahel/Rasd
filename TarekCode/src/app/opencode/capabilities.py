"""
Capability detection for installed OpenCode CLI.

No assumptions: probe actual CLI help and version.

Verified on 1.17.11:
- opencode --version  => 1.17.11
- opencode auth --help => has list, login, logout subcommands, NO switch/status/use
- opencode auth login --help => supports -p provider, -m method, no --api-key flag
- opencode auth list --help => no extra flags
- opencode auth logout --help => positional provider

This module encapsulates capability detection.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Optional


@dataclass
class OpenCodeCapabilities:
    version: str
    has_auth_list: bool = False
    has_auth_login: bool = False
    has_auth_logout: bool = False
    has_auth_switch: bool = False  # not assumed, detected
    has_auth_status: bool = False  # not assumed
    has_usage: bool = False
    has_stats: bool = False
    login_supports_provider_flag: bool = False
    login_supports_method_flag: bool = False
    logout_supports_provider_arg: bool = False
    raw_help_auth: str = ""
    raw_help_login: str = ""
    raw_help_logout: str = ""

    def supports_direct_switch(self) -> bool:
        return self.has_auth_switch

    def supports_verify(self) -> bool:
        # verify via auth list is the only reliable known mechanism
        return self.has_auth_list

    @property
    def switching_mechanism(self) -> str:
        if self.has_auth_switch:
            return "switch"
        if self.has_auth_login and self.has_auth_logout:
            return "logout_login"
        return "file_manipulation"

    def summarize(self) -> dict:
        return {
            "version": self.version,
            "has_auth_list": self.has_auth_list,
            "has_auth_login": self.has_auth_login,
            "has_auth_logout": self.has_auth_logout,
            "has_auth_switch": self.has_auth_switch,
            "has_auth_status": self.has_auth_status,
            "has_usage": self.has_usage,
            "has_stats": self.has_stats,
            "switching_mechanism": self.switching_mechanism,
            "login_supports_provider_flag": self.login_supports_provider_flag,
            "login_supports_method_flag": self.login_supports_method_flag,
        }
