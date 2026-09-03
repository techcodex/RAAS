# ADR 0003 — One Qdrant collection per project

- **Status:** Accepted
- **Date:** 2026-09-03

## Context

RAAS stores chunk embeddings in Qdrant. The `qdrant-multitenancy` guidance recommends a
single collection partitioned by a tenant payload field for "many small tenants of similar
size", and reserves collection-per-tenant for tenants with *different embedding models or
schemas*.

RAAS projects fit the exception:

- The embedder is configurable **per project** (Phase 4), so two projects can have different
  models and therefore different vector dimensions — which cannot share one collection's
  vector space.
- "Export my embeddings and load them into my own vector DB" is a product feature. A
  dedicated collection makes export a straight scroll/snapshot with no cross-tenant filtering.
- Deleting a project becomes `DROP COLLECTION`; re-embedding becomes drop + rebuild.

## Decision

One collection per project, named `project_{id}`. Payload on every point:
`document_id`, `organization_id`, `project_id`, `chunk_index`, `text`, `model_id`, plus
chunk metadata. `document_id` / `organization_id` / `project_id` are indexed for
filtered search and delete-by-document.

The embedder↔collection binding (`model_id`, `dimension`) is recorded on the **project row
in Postgres**, not in Qdrant; the rag-service refuses an upsert whose dimension does not
match an existing collection.

## Consequences

- Collection count grows with the number of projects across all orgs. Acceptable at MVP
  scale; if it becomes a problem, revisit with tiered multitenancy (shared fallback shard +
  dedicated shards for large projects) — that path is compatible with the per-project payload
  we already write.
- Tenant isolation in search is still an application-layer responsibility (always filter by
  `organization_id` / `project_id`), not just "the collection is separate".
