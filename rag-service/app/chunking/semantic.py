from __future__ import annotations

import math

from pydantic import Field

from app.chunking.base import Chunk, Chunker, ChunkerConfig, count_tokens, finalize, split_to_token_budget
from app.chunking.sentence import split_sentences
from app.embeddings.base import TextEmbedder
from app.parsing.base import ParsedDoc


class SemanticConfig(ChunkerConfig):
    breakpoint_percentile: int = Field(
        default=90, ge=50, le=99,
        description="Split where consecutive-sentence distance exceeds this percentile",
    )
    max_tokens: int = Field(default=768, ge=64, le=4096, description="Hard cap per chunk")
    buffer_sentences: int = Field(default=1, ge=0, le=5, description="Neighbouring sentences averaged into each point")


def _cosine_distance(a: list[float], b: list[float]) -> float:
    dot = sum(x * y for x, y in zip(a, b))  # noqa: B905
    na = math.sqrt(sum(x * x for x in a))
    nb = math.sqrt(sum(y * y for y in b))
    if na == 0 or nb == 0:
        return 1.0
    return 1.0 - dot / (na * nb)


def _percentile(values: list[float], pct: int) -> float:
    if not values:
        return 0.0
    ordered = sorted(values)
    rank = (pct / 100) * (len(ordered) - 1)
    lo = math.floor(rank)
    hi = math.ceil(rank)
    if lo == hi:
        return ordered[int(rank)]
    return ordered[lo] + (ordered[hi] - ordered[lo]) * (rank - lo)


class SemanticChunker(Chunker):
    name = "semantic"
    label = "Semantic"
    description = (
        "Embeds each sentence and starts a new chunk where the topic shifts (the distance "
        "between neighbouring sentences spikes). Slower — it embeds twice — but keeps ideas together."
    )
    config_model = SemanticConfig
    needs_embedder = True

    def __init__(self, embedder: TextEmbedder | None = None) -> None:
        self._embedder = embedder

    @property
    def embedder(self) -> TextEmbedder:
        if self._embedder is None:
            from app.embeddings.registry import get_embedder

            self._embedder = get_embedder()
        return self._embedder

    def chunk(self, doc: ParsedDoc, config: SemanticConfig) -> list[Chunk]:  # type: ignore[override]
        sentences = split_sentences(doc.text)
        if len(sentences) <= 2:
            return finalize(split_to_token_budget(doc.text, config.max_tokens))

        windows = self._windows(sentences, config.buffer_sentences)
        vectors = self.embedder.embed(windows)
        distances = [
            _cosine_distance(vectors[i], vectors[i + 1]) for i in range(len(vectors) - 1)
        ]
        threshold = _percentile(distances, config.breakpoint_percentile)

        chunks: list[str] = []
        current: list[str] = [sentences[0]]
        for i, distance in enumerate(distances):
            next_sentence = sentences[i + 1]
            over_budget = count_tokens(" ".join([*current, next_sentence])) > config.max_tokens
            if distance > threshold or over_budget:
                chunks.append(" ".join(current))
                current = [next_sentence]
            else:
                current.append(next_sentence)
        if current:
            chunks.append(" ".join(current))

        packed: list[str] = []
        for text in chunks:
            packed.extend(split_to_token_budget(text, config.max_tokens))
        return finalize(packed)

    @staticmethod
    def _windows(sentences: list[str], buffer: int) -> list[str]:
        if buffer <= 0:
            return sentences
        out: list[str] = []
        for i in range(len(sentences)):
            lo = max(0, i - buffer)
            hi = min(len(sentences), i + buffer + 1)
            out.append(" ".join(sentences[lo:hi]))
        return out
