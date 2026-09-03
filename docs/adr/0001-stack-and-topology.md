# ADR 0001 — Stack and service topology

- **Status:** Accepted
- **Date:** 2026-09-02

## Context

RAAS needs a web app (upload + admin + query playground) plus CPU-heavy document processing
(text extraction, chunking, embedding). These have different languages, runtimes, and scaling
profiles. Customers must be able to take their embeddings elsewhere.

## Decision

- **Frontend:** Vue 3 + Vite + TypeScript + Pinia + Tailwind.
- **Backend/API:** Laravel (PHP 8.4), Sanctum SPA auth, Redis queue, multi-tenant by
  `organization_id`.
- **Processing:** a standalone **Python FastAPI microservice** (`rag-service`). Laravel
  dispatches queue jobs that call it over HTTP; it calls back with status. Not a Laravel
  `Process` shell-out (too coupled), not a queue consumer in Python (non-standard tooling).
- **Vector DB:** **Qdrant**, self-hosted via Docker. Chosen over Pinecone (not self-hostable,
  so customers can't cheaply replicate) and pgvector (fine, but a dedicated vector store
  scales better and has a first-class snapshot/export API). One collection per project.
- **Object storage:** S3-compatible; MinIO for local dev.
- **Relational DB:** PostgreSQL.
- **Local orchestration:** `docker-compose` with all services.

## Consequences

- Two languages / CI pipelines to maintain.
- Clean scaling seam: the Python service scales independently of the API.
- Portability: per-project Qdrant collections export as a snapshot or a portable JSONL bundle.
- An HMAC shared secret is needed for backend ↔ rag-service calls.
