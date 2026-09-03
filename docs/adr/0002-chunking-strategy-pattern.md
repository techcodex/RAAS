# ADR 0002 — Chunking as a strategy pattern

- **Status:** Accepted
- **Date:** 2026-09-02

## Context

RAAS supports multiple chunking strategies (fixed, recursive, sentence, semantic, markdown
headers, code, layout/page) plus an "Auto" mode that picks one from the file type and
document statistics. Users select a strategy and tune its parameters from the UI. We will add
more strategies over time.

## Decision

In `rag-service/chunking/`:

- `base.py` — `Chunker` ABC with `chunk(doc: ParsedDoc, config) -> list[Chunk]` and a
  Pydantic `ConfigModel` per strategy (drives the UI form via JSON schema).
- One module + one class per strategy.
- `registry.py` — name → class map, `get_chunker(name)`, and `auto_select(mime, stats)`
  whose rules are unit-tested.
- `GET /strategies` returns each strategy's name, description, and config JSON schema; the
  backend proxies/caches it and the frontend renders the form from it.

Adding a strategy = one new file + one registry line + its own test file. No changes to
callers or the API shape.

## Consequences

- Open/closed; strategies are testable in isolation with fixture documents.
- The config contract is defined once (Pydantic) and reused by API + UI.
- `auto_select` is the only place with cross-strategy heuristics — keep it small and tested.
