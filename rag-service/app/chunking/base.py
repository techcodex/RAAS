from __future__ import annotations

import abc
from dataclasses import dataclass, field
from functools import lru_cache

from pydantic import BaseModel

from app.parsing.base import ParsedDoc


@lru_cache
def get_encoder():
    import tiktoken

    return tiktoken.get_encoding("cl100k_base")


def count_tokens(text: str) -> int:
    return len(get_encoder().encode(text))


def split_to_token_budget(text: str, max_tokens: int) -> list[str]:
    """Hard-split a string that exceeds the budget, on token boundaries."""
    enc = get_encoder()
    ids = enc.encode(text)
    if len(ids) <= max_tokens:
        return [text]
    return [enc.decode(ids[i : i + max_tokens]) for i in range(0, len(ids), max_tokens)]


@dataclass
class Chunk:
    text: str
    index: int
    token_count: int
    metadata: dict = field(default_factory=dict)


class ChunkerConfig(BaseModel):
    """Base for per-strategy configuration models."""

    model_config = {"extra": "forbid"}


class Chunker(abc.ABC):
    """A chunking strategy. One concrete subclass per strategy, registered in the registry."""

    name: str
    label: str
    description: str
    config_model: type[ChunkerConfig] = ChunkerConfig

    @abc.abstractmethod
    def chunk(self, doc: ParsedDoc, config: ChunkerConfig) -> list[Chunk]:
        ...

    def parse_config(self, raw: dict | None) -> ChunkerConfig:
        return self.config_model.model_validate(raw or {})

    @classmethod
    def describe(cls) -> dict:
        return {
            "name": cls.name,
            "label": cls.label,
            "description": cls.description,
            "config_schema": cls.config_model.model_json_schema(),
            "defaults": cls.config_model().model_dump(),
        }


def finalize(texts: list[str], base_metadata: list[dict] | None = None) -> list[Chunk]:
    """Turn a list of chunk strings into Chunk objects with token counts and indices."""
    chunks: list[Chunk] = []
    for i, text in enumerate(t for t in texts if t.strip()):
        meta = (base_metadata[i] if base_metadata and i < len(base_metadata) else {}) or {}
        chunks.append(Chunk(text=text.strip(), index=len(chunks), token_count=count_tokens(text), metadata=meta))
    for chunk in chunks:
        chunk.metadata.setdefault("chunk_index", chunk.index)
    return chunks
