#!/usr/bin/env python3
"""Temporary diagnostic v2 — count real usages of each string key."""
import re
import os

ROOT = os.path.dirname(os.path.abspath(__file__))
STRINGS = os.path.join(ROOT, "mobile/lib/core/strings.dart")
src = open(STRINGS, encoding="utf-8").read()

start = src.index("const Map<String, Map<String, String>> _s = {")
body = src[start:]
locales, cur, buf = {}, None, []
for line in body.splitlines()[1:]:
    m = re.match(r"^  '([a-z]{2})': \{", line)
    if m:
        if cur: locales[cur] = buf
        cur, buf = m.group(1), []
        continue
    if re.match(r"^  \},", line) and cur:
        locales[cur] = buf; cur, buf = None, []
        continue
    if cur is not None: buf.append(line)
if cur: locales[cur] = buf

KEY_RE = re.compile(r"^\s*'([a-zA-Z0-9_]+)':\s*'(.*)',?\s*$")
CONT_RE = re.compile(r"^\s*'(.*)',?\s*$")


def parse(lines):
    out, pending = {}, None
    for line in lines:
        m = KEY_RE.match(line)
        if m:
            out[m.group(1)] = m.group(2); pending = None; continue
        c = CONT_RE.match(line)
        if c and pending is not None:
            out[pending] += c.group(1); continue
        m2 = re.match(r"^\s*'([a-zA-Z0-9_]+)':", line)
        if m2:
            pending = m2.group(1); out[pending] = ""; continue
        pending = None
    return out


ar, en = parse(locales.get("ar", [])), parse(locales.get("en", []))
allkeys = sorted(set(ar) | set(en))

usage = {k: [] for k in allkeys}
usage["share_failed_media"] = []

# Any occurrence of the key as a quoted literal anywhere in lib (outside strings.dart)
libdir = os.path.join(ROOT, "mobile/lib")
for dirpath, _, files in os.walk(libdir):
    for fn in files:
        if not fn.endswith(".dart"):
            continue
        p = os.path.join(dirpath, fn)
        if os.path.abspath(p) == STRINGS:
            continue
        txt = open(p, encoding="utf-8").read()
        for k in allkeys + ["share_failed_media"]:
            if re.search(r"'" + re.escape(k) + r"'", txt):
                usage.setdefault(k, []).append(
                    os.path.relpath(p, ROOT).replace("mobile/lib/", ""))

undefined = [k for k in usage if k not in ar and k not in en]
print(f"=== USED BUT UNDEFINED ({len(undefined)}) ===")
for k in undefined:
    print(f"  {k}   <- {', '.join(sorted(set(usage.get(k, [])))[:3])}")

unused = [k for k in allkeys if not usage.get(k)]
print(f"\n=== DEFINED BUT NEVER REFERENCED ({len(unused)}) ===")
print("  " + "\n  ".join(unused))