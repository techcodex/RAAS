from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health() -> None:
    resp = client.get("/health")
    assert resp.status_code == 200
    assert resp.json()["status"] == "ok"


def test_strategies_lists_every_strategy_with_a_schema() -> None:
    resp = client.get("/strategies")
    assert resp.status_code == 200
    body = resp.json()

    names = {s["name"] for s in body["strategies"]}
    assert {"auto", "recursive", "fixed", "sentence", "markdown", "semantic"} <= names

    for strategy in body["strategies"]:
        assert "config_schema" in strategy
        assert "defaults" in strategy

    assert body["embedders"][0]["provider"] == "local"


def test_process_requires_signature() -> None:
    resp = client.post("/process", json={})
    assert resp.status_code == 422  # missing signature headers
