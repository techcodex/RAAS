# RAAS Architecture

## Overview

RAAS lets a customer turn a set of documents into a queryable knowledge base and publish a
question-answering app to their employees. Processing is asynchronous; the UI never blocks on it.

```
                    ┌─────────────┐
   Browser  ───────▶│  frontend   │  Vue 3 SPA (admin)
                    └──────┬──────┘
                           │ /api  (Sanctum bearer token)
                    ┌──────▼──────┐        ┌───────────┐
                    │   backend   │───────▶│ PostgreSQL│  app data, doc/job metadata, conversations
                    │  Laravel API│        └───────────┘
                    │             │        ┌───────────┐
                    │  RagPipeline│───────▶│   Redis   │  queue + cache
                    └──┬───────┬──┘        └───────────┘
        queued job     │       │  S3 (MinIO) — original uploaded files
                       │       └───────────────┬────────────────┐
                ┌──────▼───────┐        ┌──────▼──────┐   ┌─────▼─────┐
                │ rag-service  │───────▶│   Qdrant    │   │ Anthropic │  bring-your-own key
                │ FastAPI/Py   │ upsert/│ (1 coll./   │   │  Messages │  (RagPipeline calls
                │              │ search │  project)   │   │    API    │   this directly)
                └──────────────┘        └─────────────┘   └───────────┘
                  parse · chunk · embed · search (embed query + Qdrant)
```

## Components

### backend (Laravel)
- REST API under `/api`, versioned from Phase 1 (`/api/v1`).
- Sanctum **bearer-token** auth. Multi-tenant: every domain row carries `organization_id`,
  enforced by a global scope + tenant-resolution middleware.
- Queue jobs (Redis) for document processing; job status is mirrored on the `documents` row.
- Owns LLM provider credentials (`project_credentials`, `encrypted` cast, never serialized)
  and `RagPipeline` — the retrieval+generation service. `RagPipeline` calls rag-service
  `/search` for the grounding context, then calls the LLM directly (`App\Services\Llm`,
  interface + `AnthropicClient` on the official `anthropic-ai/sdk` — Anthropic only for now,
  more providers plug into the same interface). Conversations/messages persist per project.

### rag-service (Python FastAPI)
- Stateless worker. Pulls the original file from S3, extracts text, chunks it with a
  selected strategy (strategy pattern, one class per strategy), embeds chunks with the
  project's configured embedder, upserts to Qdrant, and calls back to the backend with status.
- Default embedder: local `fastembed` ONNX (`BAAI/bge-small-en-v1.5`, 384d). OpenAI/Cohere/
  Voyage added in Phase 4, selectable per project.
- Internal calls authenticated with an HMAC signature over `"{timestamp}." + rawBody`
  (`RAG_INTERNAL_SECRET`), 5-minute skew window. Endpoints: `/process`, `/search`
  (embeds the query + Qdrant nearest-neighbour, used by `RagPipeline`), `/embed-query`,
  `/export`, `/documents/purge`, `/collections/drop`; `/health` and `/strategies` are open.
- Chunking is a strategy pattern (`chunking/`): `fixed`, `recursive`, `sentence`, `markdown`,
  `semantic`, plus `auto`. See ADR 0002.

### Vector DB: Qdrant
- One collection per project (`project_{id}`) — see ADR 0003.
- Point payload: `document_id`, `organization_id`, `project_id`, `chunk_index`, `text`,
  chunk metadata, `model_id`. First three are indexed.
- Export: `GET /api/v1/projects/{id}/export` streams NDJSON — a manifest line
  (model id, dimension, distance, count) then one `{id, vector, payload}` per line — proxied
  from the rag-service, ready to load into the customer's own Qdrant or pgvector.

### frontend (Vue 3)
- Admin SPA. Pinia stores, Vue Router, Tailwind v4, axios client through the Vite `/api` proxy.

### employee-app (Phase 5)
- Separate minimal Vue app (or stripped route tree). Per-organization branding, restricted
  auth, no upload/settings — only ask questions and see cited answers.

## Cross-cutting decisions

- **Async everywhere** — parsing/chunking/embedding/re-embedding are queued jobs.
- **Secrets** — provider API keys encrypted at rest, masked in responses, never logged.
- **Embedder/collection binding** — a collection records its `model_id` + dimension; search
  refuses on mismatch; changing a project's embedder triggers a re-embed job.
- **Testing** — Pest (backend), pytest (rag-service, one file per chunker), Playwright (frontend e2e).

See `docs/adr/` for individual decision records.
