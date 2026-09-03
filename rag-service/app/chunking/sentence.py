from __future__ import annotations

import re

from pydantic import Field

from app.chunking.base import Chunk, Chunker, ChunkerConfig, count_tokens, finalize, split_to_token_budget
from app.parsing.base import ParsedDoc

_SENTENCE_RE = re.compile(r"(?<=[.!?])\s+(?=[A-Z0-9\"'(])")


def split_sentences(text: str) -> list[str]:
    out: list[str] = []
    for line in text.split("\n"):
        line = line.strip()
        if not line:
            continue
        out.extend(s.strip() for s in _SENTENCE_RE.split(line) if s.strip())
    return out


class SentenceConfig(ChunkerConfig):
    max_tokens: int = Field(default=384, ge=32, le=2048, description="Max tokens per chunk")
    overlap_sentences: int = Field(default=1, ge=0, le=10, description="Sentences repeated between chunks")


class SentenceChunker(Chunker):
    name = "sentence"
    label = "Sentence"
    description = (
        "Groups whole sentences up to the token budget, so no chunk ends mid-sentence. "
        "Overlaps a configurable number of sentences between chunks."
    )
    config_model = SentenceConfig

    def chunk(self, doc: ParsedDoc, config: SentenceConfig) -> list[Chunk]:  # type: ignore[override]
        sentences: list[str] = []
        for sentence in split_sentences(doc.text):
            if count_tokens(sentence) > config.max_tokens:
                sentences.extend(split_to_token_budget(sentence, config.max_tokens))
            else:
                sentences.append(sentence)

        chunks: list[str] = []
        current: list[str] = []
        tokens = 0
        for sentence in sentences:
            t = count_tokens(sentence)
            if current and tokens + t > config.max_tokens:
                chunks.append(" ".join(current))
                current = current[len(current) - config.overlap_sentences :] if config.overlap_sentences else []
                tokens = sum(count_tokens(s) for s in current)
            current.append(sentence)
            tokens += t

        if current:
            chunks.append(" ".join(current))
        return finalize(chunks)
