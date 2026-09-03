from __future__ import annotations

from pydantic import Field

from app.chunking.base import Chunk, Chunker, ChunkerConfig, count_tokens, finalize, get_encoder
from app.parsing.base import ParsedDoc


class FixedSizeConfig(ChunkerConfig):
    chunk_tokens: int = Field(default=512, ge=32, le=4096, description="Tokens per chunk")
    overlap_tokens: int = Field(default=64, ge=0, le=1024, description="Overlapping tokens between chunks")


class FixedSizeChunker(Chunker):
    name = "fixed"
    label = "Fixed size"
    description = "Sliding token window of a fixed size with a fixed overlap. Ignores document structure."
    config_model = FixedSizeConfig

    def chunk(self, doc: ParsedDoc, config: FixedSizeConfig) -> list[Chunk]:  # type: ignore[override]
        enc = get_encoder()
        ids = enc.encode(doc.text)
        step = max(1, config.chunk_tokens - config.overlap_tokens)

        texts = [
            enc.decode(ids[start : start + config.chunk_tokens])
            for start in range(0, len(ids), step)
        ]
        if len(texts) > 1 and count_tokens(texts[-1]) <= config.overlap_tokens:
            texts.pop()

        return finalize(texts)
