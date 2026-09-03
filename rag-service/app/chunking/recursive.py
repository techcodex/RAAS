from __future__ import annotations

from pydantic import Field

from app.chunking.base import Chunk, Chunker, ChunkerConfig, count_tokens, finalize, split_to_token_budget
from app.parsing.base import ParsedDoc

_SEPARATORS = ["\n\n", "\n", ". ", " ", ""]


class RecursiveConfig(ChunkerConfig):
    chunk_tokens: int = Field(default=512, ge=32, le=4096, description="Target tokens per chunk")
    overlap_tokens: int = Field(default=64, ge=0, le=1024, description="Overlap carried between chunks")


class RecursiveCharacterChunker(Chunker):
    name = "recursive"
    label = "Recursive"
    description = (
        "Splits on a hierarchy of separators (paragraph → line → sentence → word), "
        "packing pieces up to the token budget. A good general-purpose default for prose."
    )
    config_model = RecursiveConfig

    def chunk(self, doc: ParsedDoc, config: RecursiveConfig) -> list[Chunk]:  # type: ignore[override]
        pieces = self._split(doc.text, _SEPARATORS, config.chunk_tokens)
        texts = self._pack(pieces, config.chunk_tokens, config.overlap_tokens)
        return finalize(texts)

    def _split(self, text: str, separators: list[str], budget: int) -> list[str]:
        if count_tokens(text) <= budget:
            return [text] if text.strip() else []

        sep, *rest = separators
        if sep == "":
            return split_to_token_budget(text, budget)

        out: list[str] = []
        for part in text.split(sep):
            part = part if sep == "" else part + (sep if sep.strip() else "")
            if not part.strip():
                continue
            if count_tokens(part) <= budget:
                out.append(part)
            else:
                out.extend(self._split(part, rest, budget))
        return out

    def _pack(self, pieces: list[str], budget: int, overlap: int) -> list[str]:
        chunks: list[str] = []
        current: list[str] = []
        current_tokens = 0

        for piece in pieces:
            piece_tokens = count_tokens(piece)
            if current and current_tokens + piece_tokens > budget:
                chunks.append("".join(current).strip())
                current, current_tokens = self._carry_overlap(current, overlap)
            current.append(piece)
            current_tokens += piece_tokens

        if current:
            chunks.append("".join(current).strip())
        return chunks

    def _carry_overlap(self, pieces: list[str], overlap: int) -> tuple[list[str], int]:
        if overlap <= 0:
            return [], 0
        carried: list[str] = []
        total = 0
        for piece in reversed(pieces):
            t = count_tokens(piece)
            if total + t > overlap and carried:
                break
            carried.insert(0, piece)
            total += t
        return carried, total
