# RAAS — RAG-as-a-Service

A multi-tenant platform: upload documents → chunk them → generate embeddings → store in a
vector DB → ask natural-language questions answered by an LLM over those documents, and
publish a query app to your employees.

## Monorepo layout

| Path           | Stack                        | Responsibility |
|----------------|------------------------------|----------------|
| `backend/`     | Laravel 13, PHP 8.4, Sanctum | API, auth, multi-tenancy, projects/documents, queue jobs, RAG pipeline |
| `frontend/`    | Vue 3, Vite, TS, Tailwind    | Admin SPA: projects, uploads, chunking config, query playground |
| `rag-service/` | Python 3.12, FastAPI         | Text extraction, chunking strategies, embeddings, Qdrant upserts |
| `employee-app/`| Vue 3 (Phase 5)              | Minimal end-user query app published per organization |
| `docs/`        | Markdown + ADRs              | Architecture and decision records |

## Build phases

0. **Scaffold & tooling** ← current
1. Document upload (frontend + backend)
2. Chunking & embeddings (rag-service) + Qdrant + export
3. Bring-your-own-LLM in-app querying
4. Advanced retrieval, ops, multi-embedder
5. Employee query app

Full plan: `~/.claude/plans/we-are-going-to-smooth-brook.md`. See `docs/architecture.md`.

## Quick start (Docker)

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
cp rag-service/.env.example rag-service/.env
docker compose up --build
# backend     http://localhost:8000/api/health
# rag-service http://localhost:8001/health
# frontend    http://localhost:5174        (5173 in-container; 5174 on host)
# qdrant      http://localhost:6333/dashboard
# minio       http://localhost:9001        (raas / raassecret)
```

First run, from the repo root:

```bash
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
```

## Local (no Docker)

Each sub-project runs standalone — see its own `README.md`. The backend falls back to
SQLite; `rag-service` needs Python 3.11+ (system Python here is 3.9, so prefer Docker).

## Checks

```bash
cd backend      && vendor/bin/pest
cd frontend     && npm run typecheck && npm run e2e   # e2e needs the app running
cd rag-service  && .venv/bin/ruff check . && .venv/bin/pytest
```

> Playwright browsers: first run `cd frontend && npx playwright install chromium`.

## Claude Code plugins used

- `qdrant-skills`, `playwright`, `pyright-lsp`, `php-lsp` — user scope.
- Laravel Boost — runs as a project MCP server (`.mcp.json`, targets `backend/`); the
  marketplace plugin is disabled since it can't point at a monorepo subdirectory.
- LSP binaries: `npm i -g pyright intelephense`.
