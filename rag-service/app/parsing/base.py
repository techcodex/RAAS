from __future__ import annotations

from dataclasses import dataclass, field


@dataclass
class Block:
    """A contiguous piece of a document with light structural metadata."""

    text: str
    kind: str = "paragraph"  # paragraph | heading | list_item | table_row | code
    page: int | None = None
    heading_path: list[str] = field(default_factory=list)
    level: int | None = None  # heading depth, when kind == "heading"


@dataclass
class ParsedDoc:
    """Normalized representation of an extracted document."""

    blocks: list[Block]
    mime_type: str
    page_count: int | None = None

    @property
    def text(self) -> str:
        return "\n\n".join(block.text for block in self.blocks if block.text.strip())

    @property
    def char_count(self) -> int:
        return len(self.text)
