# rag-service

Python FastAPI microservice for RAAS. Owns text extraction, chunking (strategy pattern),
embedding generation, and Qdrant upserts. Called by the Laravel backend via queued jobs.

## Endpoints

| Method | Path          | Phase | Purpose |
|--------|---------------|-------|---------|
| GET    | `/health`     | 0     | Liveness probe |
| GET    | `/strategies` | 2     | Chunking strategies + their config JSON schema |
| POST   | `/process`    | 2     | Parse → chunk → embed → upsert a document |
| POST   | `/embed-query`| 3     | Embed a single query string |
| POST   | `/rerank`     | 3     | Cross-encoder re-rank of candidates |

## Local dev

```bash
python -m venv .venv && source .venv/bin/activate
pip install -r requirements-dev.txt
cp .env.example .env
uvicorn app.main:app --reload --port 8001
```

Or via the repo-root `docker-compose up rag-service`.

## Checks

```bash
ruff check . && pyright && pytest
```
