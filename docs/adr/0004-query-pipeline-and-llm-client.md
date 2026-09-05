# ADR 0004 — Query pipeline: search stays in rag-service, LLM calls happen in Laravel

- **Status:** Accepted
- **Date:** 2026-09-04

## Context

Phase 3 adds in-app querying: embed the question, find the nearest chunks in Qdrant, and
answer with an LLM using an API key the customer supplies per project. Two design questions:

1. Does Laravel talk to Qdrant directly, or does the rag-service own all vector operations?
2. Where does the LLM call happen — rag-service (Python) or Laravel (PHP)?

## Decision

**1. All Qdrant access stays in the rag-service**, behind a new `POST /search` endpoint that
embeds the query and runs the nearest-neighbour search in one call. Laravel never talks to
Qdrant directly (no PHP Qdrant client, no raw REST calls from the backend). This keeps every
vector-store concern — collections, payload shape, filtering, the embedder used — in the one
place that already owns `QdrantStore`, and matches the Phase 2 boundary
(backend ↔ rag-service only, via the signed HTTP contract).

**2. The LLM call happens in Laravel**, not the rag-service. `RagPipeline` (backend) calls
`RagClient::search()` for context, then calls the LLM directly through `App\Services\Llm\
LlmClient` (interface) → `AnthropicClient` (official `anthropic-ai/sdk` PHP package). Reasons:
- Conversation history and citations are relational data (`conversations`, `messages`) that
  already live in Postgres; assembling the prompt and persisting the turn in the same service
  avoids a second round trip through rag-service just to shuttle that state.
- Provider credentials are entered and encrypted in Laravel (`project_credentials`); keeping
  the call there avoids passing a decrypted key over the internal HTTP hop.
- The rag-service's job is text/vector work (extraction, chunking, embeddings, search) — it
  has no other reason to depend on an LLM SDK or hold conversation state.

**Provider scope:** Anthropic only for the initial Phase 3 build, via `LlmClient` as an
interface so another provider is a new class, not a rewrite. Default model `claude-opus-5`;
the customer's per-project settings can select `claude-sonnet-5` / `claude-haiku-4-5` instead
— that's a product choice made by the project owner for their own use case, not a default
downgrade for cost.

**Update (same day):** added **Google Gemini** as a second provider. Anthropic requires
billing to be set up before a key can even be minted, which blocks anyone (including this
project's own developer) from testing the feature without payment. Gemini's free tier issues
keys with no credit card at aistudio.google.com/apikey, so it's the practical way to exercise
the pipeline end-to-end at zero cost. `App\Support\LlmProviders` now holds the
provider→model list (mirrored in `LlmSettings.vue`), and `LlmClientResolver` maps a
credential's `provider` string to its `LlmClient`. Gemini's free tier is Flash-class models
only (Pro moved to paid-only in April 2026), so `LlmProviders::MODELS['gemini']` deliberately
lists only Flash models — offering Pro in the dropdown would just produce a confusing
runtime failure for a free-tier key. `GeminiClient` calls the raw REST API (`x-goog-api-key`
header, `models/{model}:generateContent`) since there is no official Google PHP SDK for
Gemini; Anthropic's official `anthropic-ai/sdk` is used for that provider as before.

**Update (2026-09-04, issue [#1](https://github.com/techcodex/RAAS/issues/1)):** added
**Groq** as a third provider — Gemini's free tier proved too rate-limited for comfortable
testing (10 req/min, 250/day on Flash) and errors out mid-session. Groq's free tier
(console.groq.com/keys, no credit card) has much more headroom and is fast (custom inference
hardware, not CPU-bound like a local model). `GroqClient` calls Groq's OpenAI-compatible REST
API (`Authorization: Bearer`, `POST /openai/v1/chat/completions`) — no official Groq PHP SDK
either, so raw `Http` calls like `GeminiClient`. Default model `llama-3.3-70b-versatile`.
Three providers now share the same `LlmClient` interface with no changes to `RagPipeline`,
the query endpoint, or the frontend chat UI — only `LlmProviders`, `LlmClientResolver`, and
`LlmSettings.vue` needed a new entry each, which is the seam this abstraction was built for.

**Response shape:** plain JSON (`{data: message, conversation_id}`), not SSE streaming. A
grounded RAG answer is a single bounded completion, not a long agentic run — synchronous is
simpler to build, test, and reason about. Streaming is a follow-up if latency becomes a
product problem; it doesn't change the pipeline, only how the final `LlmAnswer` is delivered.

## Consequences

- `RagClient::search()` is the only new backend↔rag-service call this phase; `/embed-query`
  (built in Phase 2) is unused by the pipeline now but stays available for a future
  Laravel-side use (e.g. server-side reranking) without another round trip to change.
- Provider errors (bad key, rate limit, connection failure) are caught in `AnthropicClient`
  and re-thrown as `RagException` with a message that's safe to return to the API caller
  as-is — no stack traces, no leaking SDK internals.
- Every LLM answer is grounded by construction: the system prompt contains only the numbered
  excerpts `/search` returned, with an explicit instruction to say "I don't know" rather than
  answer from outside knowledge when nothing relevant was retrieved.
