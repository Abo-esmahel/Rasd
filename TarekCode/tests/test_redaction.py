from app.utils.redaction import redact_text, redact_dict, fingerprint

def test_redact_text():
    s = "key sk-abc123XYZ7890 more text"
    r = redact_text(s)
    assert "sk-abc123" not in r
    assert "••••" in r
    assert "more text" in r

def test_redact_dict():
    d = {"api_key": "sk-test123456789", "name": "Personal 01", "nested": {"token": "sk-nested987654321"}}
    r = redact_dict(d)
    assert r["api_key"] == "••••••••"
    assert r["nested"]["token"] == "••••••••"
    assert r["name"] == "Personal 01"

def test_redact_dict_string_values():
    d = {"msg": "error with sk-abcXYZ1234567890"}
    r = redact_dict(d)
    assert "sk-abcXYZ" not in str(r)
    assert "••••" in r["msg"]

def test_fingerprint():
    fp = fingerprint("sk-Uit55ytlccQLnbfdlaGrMY98VkTNOJYvHmgHmoVtgBK9RuJMN3w62DtRXAaTF2EN")
    assert fp.startswith("sk-")
    assert "••••" in fp
    assert "sk-Uit55" not in fp
    assert fp != "sk-Uit55ytlccQLnbfdlaGrMY98VkTNOJYvHmgHmoVtgBK9RuJMN3w62DtRXAaTF2EN"
    # last 4 chars visible but not enough to reconstruct
    assert fp.endswith("2EN")

def test_fingerprint_short():
    assert fingerprint("") == "••••••••"
    assert fingerprint("short") == "••••••••"
