from __future__ import annotations

from app.chunking.base import Chunker
from app.chunking.fixed import FixedSizeChunker
from app.chunking.markdown import MarkdownHeaderChunker
from app.chunking.recursive import RecursiveCharacterChunker
from app.chunking.semantic import SemanticChunker
from app.chunking.sentence import SentenceChunker
from app.embeddings.base import TextEmbedder
from app.parsing.base import ParsedDoc

_STRATEGIES: dict[str, type[Chunker]] = {
    cls.name: cls
    for cls in (
        RecursiveCharacterChunker,
        FixedSizeChunker,
        SentenceChunker,
        MarkdownHeaderChunker,
        SemanticChunker,
    )
}

AUTO = "auto"


def strategy_names() -> list[str]:
    return [AUTO, *_STRATEGIES]


def list_strategies() -> list[dict]:
    auto = {
        "name": AUTO,
        "label": "Auto",
        "description": "Pick a strategy from the file type and document shape.",
        "config_schema": {"type": "object", "properties": {}},
        "defaults": {},
    }
    return [auto, *(cls.describe() for cls in _STRATEGIES.values())]


def get_chunker(name: str, embedder: TextEmbedder | None = None) -> Chunker:
    if name == AUTO:
        raise ValueError("Resolve 'auto' with auto_select() before calling get_chunker().")
    try:
        cls = _STRATEGIES[name]
    except KeyError:
        raise ValueError(f"Unknown chunking strategy '{name}'. Available: {', '.join(strategy_names())}") from None

    if cls is SemanticChunker:
        return SemanticChunker(embedder=embedder)
    return cls()


def auto_select(doc: ParsedDoc, filename: str = "") -> str:
    """Heuristic strategy pick. Kept small and covered by unit tests."""
    ext = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""
    mime = doc.mime_type
    heading_blocks = sum(1 for b in doc.blocks if b.kind == "heading")

    if ext in {"md", "markdown"} or mime == "text/markdown":
        return MarkdownHeaderChunker.name
    if mime == "text/html" and heading_blocks >= 3:
        return MarkdownHeaderChunker.name
    if mime == "text/csv":
        return SentenceChunker.name
    if heading_blocks >= 5 and heading_blocks >= len(doc.blocks) * 0.1:
        return MarkdownHeaderChunker.name
    if doc.char_count < 20_000:
        return SentenceChunker.name
    return RecursiveCharacterChunker.name
