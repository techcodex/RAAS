from __future__ import annotations

from pydantic import Field

from app.chunking.base import Chunk, Chunker, ChunkerConfig, count_tokens, finalize, split_to_token_budget
from app.parsing.base import ParsedDoc


class MarkdownHeaderConfig(ChunkerConfig):
    max_tokens: int = Field(default=768, ge=64, le=4096, description="Split sections larger than this")
    min_tokens: int = Field(default=64, ge=0, le=1024, description="Merge sections smaller than this into the next")
    include_heading_path: bool = Field(default=True, description="Prefix each chunk with its heading breadcrumb")


class MarkdownHeaderChunker(Chunker):
    name = "markdown"
    label = "Markdown headers"
    description = (
        "Splits on the heading hierarchy, keeping each section whole and tagging it with its "
        "heading breadcrumb. Oversized sections fall back to a token split. Best for structured docs."
    )
    config_model = MarkdownHeaderConfig

    def chunk(self, doc: ParsedDoc, config: MarkdownHeaderConfig) -> list[Chunk]:  # type: ignore[override]
        sections: list[tuple[list[str], list[str]]] = []  # (heading_path, body lines)
        current_path: list[str] = []
        current_body: list[str] = []

        def flush() -> None:
            if current_body or current_path:
                sections.append((list(current_path), list(current_body)))

        for block in doc.blocks:
            if block.kind == "heading":
                flush()
                current_path = block.heading_path or [block.text]
                current_body = []
            else:
                current_body.append(block.text)
        flush()

        texts: list[str] = []
        metas: list[dict] = []
        carry = ""
        carry_path: list[str] = []
        for path, body in sections:
            section_text = "\n\n".join(body).strip()
            if not section_text:
                continue
            if carry:
                section_text = f"{carry}\n\n{section_text}"
                path = carry_path or path
                carry = ""

            if count_tokens(section_text) < config.min_tokens:
                carry = section_text
                carry_path = path
                continue

            header = " › ".join(path)
            for part in split_to_token_budget(section_text, config.max_tokens):
                body_text = f"{header}\n\n{part}" if config.include_heading_path and header else part
                texts.append(body_text)
                metas.append({"heading_path": path})

        if carry:
            header = " › ".join(carry_path)
            texts.append(f"{header}\n\n{carry}" if config.include_heading_path and header else carry)
            metas.append({"heading_path": carry_path})

        return finalize(texts, metas)
