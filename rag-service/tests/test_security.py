import time

from fastapi.testclient import TestClient

from app.config import get_settings
from app.security import _sign

get_settings.cache_clear()


def _client() -> TestClient:
    from app.main import app

    return TestClient(app)


def test_valid_signature_passes_auth_and_reaches_handler() -> None:
    settings = get_settings()
    ts = str(int(time.time()))
    body = b'{"text":"hello"}'
    sig = _sign(settings.internal_secret, ts, body)

    resp = _client().post(
        "/embed-query",
        content=body,
        headers={
            "Content-Type": "application/json",
            "X-RAG-Timestamp": ts,
            "X-RAG-Signature": sig,
        },
    )
    # Auth passed; failure here (if any) is model download, not 401.
    assert resp.status_code != 401


def test_bad_signature_is_rejected() -> None:
    ts = str(int(time.time()))
    resp = _client().post(
        "/embed-query",
        content=b"{}",
        headers={"X-RAG-Timestamp": ts, "X-RAG-Signature": "deadbeef"},
    )
    assert resp.status_code == 401


def test_stale_timestamp_is_rejected() -> None:
    old = str(int(time.time()) - 9999)
    sig = _sign(get_settings().internal_secret, old, b"{}")
    resp = _client().post(
        "/embed-query",
        content=b"{}",
        headers={"X-RAG-Timestamp": old, "X-RAG-Signature": sig},
    )
    assert resp.status_code == 401
