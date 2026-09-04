import json
import time
from unittest.mock import MagicMock, patch

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
    return client.post("/search", content=body, headers=_headers(body))


def test_search_returns_matches_with_metadata() -> None:
    payload = {"collection": "project_1", "query": "travel policy", "top_k": 2}
    hit = MagicMock(
        id="9c1d73ff",
        score=0.87,
        payload={
            "document_id": 5,
            "organization_id": 1,
            "project_id": 1,
            "chunk_index": 2,
            "text": "Book travel 14 days ahead.",
            "model_id": "BAAI/bge-small-en-v1.5",
            "heading_path": ["Handbook", "Travel"],
        },
    )

    with patch("app.main.QdrantStore") as MockStore:
        instance = MockStore.return_value
        instance.client.collection_exists.return_value = True
        instance.search.return_value = [hit]

        resp = _post(payload)

    assert resp.status_code == 200
    body = resp.json()
    assert body["dimension"] == 384
    result = body["results"][0]
    assert result["document_id"] == 5
    assert result["chunk_index"] == 2
    assert result["text"] == "Book travel 14 days ahead."
    assert result["metadata"] == {"heading_path": ["Handbook", "Travel"]}
    instance.search.assert_called_once()
    _, kwargs = instance.search.call_args
    assert kwargs["limit"] == 2


def test_search_404s_on_missing_collection() -> None:
    with patch("app.main.QdrantStore") as MockStore:
        MockStore.return_value.client.collection_exists.return_value = False
        resp = _post({"collection": "project_999", "query": "x"})

    assert resp.status_code == 404


def test_search_requires_signature() -> None:
    resp = client.post("/search", json={"collection": "project_1", "query": "x"})
    assert resp.status_code == 422
