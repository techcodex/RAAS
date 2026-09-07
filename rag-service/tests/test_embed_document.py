import json
import time
from unittest.mock import patch

from fastapi.testclient import TestClient

from app.config import get_settings
from app.main import app
from app.security import _sign

client = TestClient(app)


def _headers(body: bytes) -> dict[str, str]:
    ts = str(int(time.time()))
    sig = _sign(get_settings().internal_secret, ts, body)
    return {"X-RAG-Timestamp": ts, "X-RAG-Signature": sig, "Content-Type": "application/json"}


def _post(payload: dict):
    body = json.dumps(payload).encode()
    return client.post("/documents/embed", content=body, headers=_headers(body))


def _payload(**overrides) -> dict:
    base = {
        "collection": "project_1",
        "organization_id": 1,
        "project_id": 1,
        "document_id": 7,
        "embedder": {"provider": "local", "model": "BAAI/bge-base-en-v1.5"},
        "chunks": [
            {"index": 1, "text": "Second chunk.", "metadata": {}},
            {"index": 0, "text": "First chunk.", "metadata": {"heading_path": ["Intro"]}},
        ],
        "replace": True,
    }
    base.update(overrides)
    return base


def test_embeds_stored_chunks_and_upserts_at_the_model_dimension() -> None:
    with patch("app.pipeline.QdrantStore") as MockStore:
        instance = MockStore.return_value

        resp = _post(_payload())

    assert resp.status_code == 200, resp.text
    body = resp.json()
    assert body["model_id"] == "BAAI/bge-base-en-v1.5"
    assert body["dimension"] == 768
    assert body["chunk_count"] == 2

    instance.ensure_collection.assert_called_once_with(768, "Cosine", "BAAI/bge-base-en-v1.5")
    instance.delete_document.assert_called_once_with(7)

    (points,), _ = instance.upsert.call_args
    # Chunks are embedded in index order regardless of request order.
    assert [p.payload["chunk_index"] for p in points] == [0, 1]
    assert points[0].payload["heading_path"] == ["Intro"]
    assert len(points[0].vector) == 768


def test_does_not_delete_when_replace_is_false() -> None:
    with patch("app.pipeline.QdrantStore") as MockStore:
        instance = MockStore.return_value
        _post(_payload(replace=False))

    instance.delete_document.assert_not_called()


def test_rejects_an_unknown_model() -> None:
    with patch("app.pipeline.QdrantStore"):
        resp = _post(_payload(embedder={"provider": "local", "model": "no/such-model"}))

    assert resp.status_code == 422


def test_requires_a_signature() -> None:
    resp = client.post("/documents/embed", json=_payload())
    assert resp.status_code == 422
