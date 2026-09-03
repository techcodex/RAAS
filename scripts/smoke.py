#!/usr/bin/env python3
"""
End-to-end smoke test for RAAS (Phases 0-2) against the running Docker stack.

    docker compose up -d
    docker compose exec backend php artisan migrate --force
    python3 scripts/smoke.py

Exercises auth, tenancy, upload/validation, every chunking strategy, chunk
preview, embeddings export, Qdrant state, and the delete/purge/drop paths.
Needs only the Python standard library.
"""
import io
import json
import os
import sys
import time
import urllib.error
import urllib.request
from typing import Any, Tuple

BASE = os.environ.get("RAAS_API", "http://localhost:8000/api/v1")
QDRANT = os.environ.get("RAAS_QDRANT", "http://localhost:6333")
SCRATCH = os.path.dirname(os.path.abspath(__file__))

results = []


def check(name, ok, detail=""):
    results.append((name, ok, detail))
    mark = "PASS" if ok else "FAIL"
    print(f"  [{mark}] {name}" + (f"  — {detail}" if detail else ""))


def req(method, path, token=None, json_body=None, multipart=None, raw=False, base=BASE) -> Tuple[int, Any]:
    url = base + path if path.startswith("/") else path
    headers = {"Accept": "application/json"}
    data = None
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if json_body is not None:
        data = json.dumps(json_body).encode()
        headers["Content-Type"] = "application/json"
    if multipart is not None:
        boundary = "----raastest"
        buf = io.BytesIO()
        for field, (fname, content, ctype) in multipart.items():
            buf.write(f"--{boundary}\r\n".encode())
            buf.write(f'Content-Disposition: form-data; name="{field}"; filename="{fname}"\r\n'.encode())
            buf.write(f"Content-Type: {ctype}\r\n\r\n".encode())
            buf.write(content if isinstance(content, bytes) else content.encode())
            buf.write(b"\r\n")
        buf.write(f"--{boundary}--\r\n".encode())
        data = buf.getvalue()
        headers["Content-Type"] = f"multipart/form-data; boundary={boundary}"
    r = urllib.request.Request(url, data=data, headers=headers, method=method)

    def parse(status, body):
        if raw:
            return status, body
        try:
            return status, json.loads(body or "null")
        except Exception:
            return status, {"_nonjson": body[:200].decode("utf-8", "replace")}

    try:
        with urllib.request.urlopen(r) as resp:
            return parse(resp.status, resp.read())
    except urllib.error.HTTPError as e:
        return parse(e.code, e.read())


def register(suffix):
    email = f"it-{suffix}-{int(time.time()*1000)}@example.com"
    st, body = 0, None
    for _ in range(4):
        st, body = req("POST", "/auth/register", json_body={
            "name": "IT", "email": email, "password": "password", "password_confirmation": "password"})
        if st == 201:
            return body["token"], email
        if st != 429:
            break
        time.sleep(20)
    raise AssertionError((st, body))


def wait_status(token, doc_id, want=("ready", "failed"), timeout=90):
    body = {"data": {"status": "unknown"}}
    for _ in range(timeout):
        _, body = req("GET", f"/documents/{doc_id}", token=token)
        if body["data"]["status"] in want:
            break
        time.sleep(1)
    return body["data"]


print("\n═══ 1. AUTH ═══")
tok, email = register("auth")
check("register returns token + org", True)
st, b = req("POST", "/auth/login", json_body={"email": email, "password": "password"})
check("login with correct password", st == 200 and "token" in b)
st, b = req("POST", "/auth/login", json_body={"email": email, "password": "wrong"})
check("login rejects wrong password (422)", st == 422)
st, b = req("GET", "/auth/me", token=tok)
check("GET /auth/me returns user", st == 200 and b["data"]["email"] == email)
st, b = req("GET", "/projects")
check("unauthenticated → 401", st == 401)
st, b = req("POST", "/auth/register", json_body={
    "name": "x", "email": email, "password": "password", "password_confirmation": "password"})
check("duplicate email rejected (422)", st == 422)

print("\n═══ 2. PROJECTS + TENANT ISOLATION ═══")
st, b = req("POST", "/projects", token=tok, json_body={"name": "Handbook", "description": "HR"})
pid = b["data"]["id"]
check("create project", st == 201 and b["data"]["name"] == "Handbook")
st, b = req("GET", "/projects", token=tok)
check("list shows own project", st == 200 and any(p["id"] == pid for p in b["data"]))
st, b = req("PATCH", f"/projects/{pid}", token=tok, json_body={"name": "Handbook v2"})
check("update project", st == 200 and b["data"]["name"] == "Handbook v2")

tok2, _ = register("tenant")
st, b = req("GET", f"/projects/{pid}", token=tok2)
check("other tenant cannot see project (404)", st == 404)
st, b = req("POST", f"/projects/{pid}/documents", token=tok2,
            multipart={"files[]": ("x.txt", "hi", "text/plain")})
check("other tenant cannot upload to project (404)", st == 404)

print("\n═══ 3. UPLOAD — FILE TYPES + VALIDATION ═══")
samples = {
    "notes.txt": ("First paragraph about onboarding.\n\nSecond paragraph about benefits.", "text/plain"),
    "guide.md": ("# Guide\n\n## Setup\n\nInstall the CLI. " * 3 + "\n\n## Usage\n\nRun the command. " * 3, "text/markdown"),
    "page.html": ("<html><body><h1>Policy</h1><p>" + "Remote work is allowed. " * 10 + "</p></body></html>", "text/html"),
    "data.csv": ("name,role,team\nAda,Engineer,Platform\nGrace,Admiral,Navy\n", "text/csv"),
}
doc_ids = {}
for fname, (content, ctype) in samples.items():
    st, b = req("POST", f"/projects/{pid}/documents", token=tok,
                multipart={"files[]": (fname, content, ctype)})
    ok = st == 201 and b["data"][0]["status"] == "uploaded"
    doc_ids[fname] = b["data"][0]["id"] if ok else None
    check(f"upload {fname}", ok)

_pdf_path=os.path.join(SCRATCH,"sample.pdf")
if os.path.exists(_pdf_path):
    pdf_bytes=open(_pdf_path,"rb").read()
else:
    pdf_bytes=None
st, b = req("POST", f"/projects/{pid}/documents", token=tok,
            multipart={"files[]": ("sample.pdf", pdf_bytes, "application/pdf")})
doc_ids["sample.pdf"] = b["data"][0]["id"] if st == 201 else None
check("upload sample.pdf", st == 201)

st, b = req("POST", f"/projects/{pid}/documents", token=tok,
            multipart={"files[]": ("evil.exe", b"MZ\x90\x00", "application/octet-stream")})
check("reject .exe (422)", st == 422)

big = "x" * (52 * 1024 * 1024)  # just over the 50 MB limit
st, b = req("POST", f"/projects/{pid}/documents", token=tok,
            multipart={"files[]": ("big.txt", big, "text/plain")})
check("reject oversized file (4xx)", 400 <= st < 500, f"status {st}")

print("\n═══ 4. CHUNKING STRATEGIES ═══")
for strategy in ["recursive", "fixed", "sentence", "markdown", "semantic", "auto"]:
    did = doc_ids["guide.md"]
    st, b = req("POST", f"/documents/{did}/process", token=tok,
                json_body={"strategy": strategy, "strategy_config": {}})
    if st != 202:
        check(f"strategy '{strategy}'", False, f"process returned {st}")
        continue
    final = wait_status(tok, did)
    ok = final["status"] == "ready" and final["chunk_count"] >= 1
    check(f"strategy '{strategy}'", ok,
          f"{final['chunk_count']} chunks, resolved={final['chunking_strategy']}" if ok else final.get("error_message"))

st, b = req("POST", f"/documents/{did}/process", token=tok, json_body={"strategy": "telepathy"})
check("unknown strategy rejected (422)", st == 422)

print("\n═══ 5. PROCESS EACH FILE TYPE ═══")
for fname, did in doc_ids.items():
    if did is None:
        continue
    req("POST", f"/documents/{did}/process", token=tok, json_body={"strategy": "auto"})
for fname, did in doc_ids.items():
    if did is None:
        continue
    final = wait_status(tok, did)
    check(f"process {fname}", final["status"] == "ready",
          f"{final['chunk_count']} chunks via {final['chunking_strategy']}" if final["status"] == "ready" else final.get("error_message"))

print("\n═══ 6. CHUNK PREVIEW ═══")
did = doc_ids["guide.md"]
st, b = req("GET", f"/documents/{did}/chunks", token=tok)
idxs = [c["chunk_index"] for c in b["data"]]
check("chunks listed in order", st == 200 and idxs == sorted(idxs) and len(idxs) >= 1,
      f"{b['meta']['total']} total")
check("chunk has content + token_count", all(c["content"] and c["token_count"] > 0 for c in b["data"]))
st, b = req("GET", f"/documents/{doc_ids['guide.md']}/chunks", token=tok2)
check("other tenant cannot read chunks (404)", st == 404)

print("\n═══ 7. EXPORT ═══")
st, raw = req("GET", f"/projects/{pid}/export", token=tok, raw=True)
lines = raw.decode().strip().split("\n") if st == 200 else []
manifest = json.loads(lines[0]) if lines else {}
check("export streams NDJSON", st == 200 and manifest.get("type") == "raas.embeddings.manifest",
      f"dim={manifest.get('dimension')}, points={manifest.get('point_count')}")
if len(lines) > 1:
    pt = json.loads(lines[1])
    check("export point has id + vector + payload",
          "id" in pt and len(pt.get("vector", [])) == manifest.get("dimension") and "payload" in pt)
st, b = req("GET", f"/projects/{pid}/export", token=tok2)
check("other tenant cannot export (404)", st == 404)

print("\n═══ 8. QDRANT STATE ═══")
st, b = req("GET", f"{QDRANT}/collections/project_{pid}", base="")
pts_before = b["result"]["points_count"]
check("qdrant collection exists with points", st == 200 and pts_before > 0, f"{pts_before} points")

print("\n═══ 9. DELETE DOCUMENT → VECTOR PURGE ═══")
del_doc = doc_ids["data.csv"]
st, chunks_b = req("GET", f"/documents/{del_doc}/chunks", token=tok)
csv_chunks = chunks_b["meta"]["total"]
st, _ = req("DELETE", f"/documents/{del_doc}", token=tok)
check("delete document (204)", st == 204)
time.sleep(3)
st, b = req("GET", f"{QDRANT}/collections/project_{pid}", base="")
pts_after = b["result"]["points_count"]
check("qdrant points purged for deleted doc", pts_after == pts_before - csv_chunks,
      f"{pts_before} → {pts_after} (removed {csv_chunks})")
st, b = req("GET", f"/documents/{del_doc}", token=tok)
check("deleted document is gone (404)", st == 404)

print("\n═══ 10. ERROR PATH — UNPARSEABLE PDF ═══")
st, b = req("POST", f"/projects/{pid}/documents", token=tok,
            multipart={"files[]": ("scanned.pdf", b"%PDF-1.4\n%broken\n", "application/pdf")})
bad_id = b["data"][0]["id"]
req("POST", f"/documents/{bad_id}/process", token=tok, json_body={"strategy": "auto"})
final = wait_status(tok, bad_id)
check("bad PDF → failed status with message", final["status"] == "failed" and bool(final["error_message"]),
      final.get("error_message", "")[:80])

print("\n═══ 11. DELETE PROJECT → COLLECTION DROP ═══")
st, _ = req("DELETE", f"/projects/{pid}", token=tok)
check("delete project (204)", st == 204)
time.sleep(3)
st, b = req("GET", f"{QDRANT}/collections/project_{pid}", base="")
check("qdrant collection dropped", st == 404)

print("\n" + "═" * 50)
passed = sum(1 for _, ok, _ in results if ok)
total = len(results)
print(f"  {passed}/{total} checks passed")
failed = [(n, d) for n, ok, d in results if not ok]
if failed:
    print("\n  FAILURES:")
    for n, d in failed:
        print(f"    ✗ {n}  {d}")
    sys.exit(1)
print("  ALL GREEN ✓")
