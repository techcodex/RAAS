from __future__ import annotations

import csv
import io

from app.parsing.base import Block, ParsedDoc


class ExtractionError(Exception):
    """Raised when a document's text cannot be extracted."""


def extract(content: bytes, filename: str, mime_type: str | None = None) -> ParsedDoc:
    """Extract a ParsedDoc from raw file bytes, dispatching on file extension."""
    ext = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""
    mime = mime_type or ""

    try:
        if ext == "pdf" or mime == "application/pdf":
            return _extract_pdf(content)
        if ext in {"docx"} or mime.endswith("wordprocessingml.document"):
            return _extract_docx(content)
        if ext in {"md", "markdown"} or mime == "text/markdown":
            return _extract_markdown(content)
        if ext in {"html", "htm"} or mime == "text/html":
            return _extract_html(content)
        if ext == "csv" or mime == "text/csv":
            return _extract_csv(content)
        if ext in {"txt", "text", ""} or mime.startswith("text/"):
            return _extract_text(content)
    except ExtractionError:
        raise
    except Exception as exc:  # noqa: BLE001 - surface any parser failure uniformly
        raise ExtractionError(f"Failed to parse {filename}: {exc}") from exc

    raise ExtractionError(f"Unsupported file type: {filename} ({mime or ext})")


def _decode(content: bytes) -> str:
    for encoding in ("utf-8", "utf-16", "latin-1"):
        try:
            return content.decode(encoding)
        except UnicodeDecodeError:
            continue
    return content.decode("utf-8", errors="replace")


def _extract_text(content: bytes) -> ParsedDoc:
    text = _decode(content)
    blocks = [
        Block(text=para.strip())
        for para in text.replace("\r\n", "\n").split("\n\n")
        if para.strip()
    ]
    return ParsedDoc(blocks=blocks or [Block(text=text.strip())], mime_type="text/plain")


def _extract_markdown(content: bytes) -> ParsedDoc:
    from markdown_it import MarkdownIt

    text = _decode(content)
    md = MarkdownIt()
    tokens = md.parse(text)

    blocks: list[Block] = []
    heading_path: list[str] = []
    i = 0
    while i < len(tokens):
        tok = tokens[i]
        if tok.type == "heading_open":
            level = int(tok.tag[1])
            inline = tokens[i + 1]
            title = inline.content.strip()
            heading_path = heading_path[: level - 1] + [title]
            blocks.append(
                Block(text=title, kind="heading", level=level, heading_path=list(heading_path))
            )
            i += 3
            continue
        if tok.type == "inline" and tok.content.strip():
            blocks.append(
                Block(text=tok.content.strip(), heading_path=list(heading_path))
            )
        i += 1

    return ParsedDoc(blocks=blocks, mime_type="text/markdown")


def _extract_html(content: bytes) -> ParsedDoc:
    from bs4 import BeautifulSoup

    soup = BeautifulSoup(content, "lxml")
    for tag in soup(["script", "style", "noscript"]):
        tag.decompose()

    blocks: list[Block] = []
    heading_path: list[str] = []
    for element in soup.find_all(["h1", "h2", "h3", "h4", "h5", "h6", "p", "li", "pre"]):
        text = element.get_text(" ", strip=True)
        if not text:
            continue
        name = element.name
        if name and name.startswith("h") and len(name) == 2:
            level = int(name[1])
            heading_path = heading_path[: level - 1] + [text]
            blocks.append(
                Block(text=text, kind="heading", level=level, heading_path=list(heading_path))
            )
        else:
            kind = "code" if name == "pre" else "list_item" if name == "li" else "paragraph"
            blocks.append(Block(text=text, kind=kind, heading_path=list(heading_path)))

    return ParsedDoc(blocks=blocks, mime_type="text/html")


def _extract_csv(content: bytes) -> ParsedDoc:
    text = _decode(content)
    reader = csv.reader(io.StringIO(text))
    rows = list(reader)
    if not rows:
        return ParsedDoc(blocks=[], mime_type="text/csv")

    header = rows[0]
    blocks: list[Block] = [Block(text=", ".join(header), kind="heading", level=1)]
    for row in rows[1:]:
        pairs = [f"{h}: {v}" for h, v in zip(header, row)]  # noqa: B905
        blocks.append(Block(text="; ".join(pairs), kind="table_row"))
    return ParsedDoc(blocks=blocks, mime_type="text/csv")


def _extract_pdf(content: bytes) -> ParsedDoc:
    import fitz  # pymupdf

    blocks: list[Block] = []
    with fitz.open(stream=content, filetype="pdf") as doc:
        page_count = doc.page_count
        for page_index in range(page_count):
            page = doc[page_index]
            for para in page.get_text("text").split("\n\n"):  # type: ignore[attr-defined]
                cleaned = " ".join(para.split())
                if cleaned:
                    blocks.append(Block(text=cleaned, page=page_index + 1))

    if not blocks:
        raise ExtractionError("PDF contains no extractable text (it may be scanned images).")

    return ParsedDoc(blocks=blocks, mime_type="application/pdf", page_count=page_count)


def _extract_docx(content: bytes) -> ParsedDoc:
    import docx

    document = docx.Document(io.BytesIO(content))
    blocks: list[Block] = []
    heading_path: list[str] = []

    for para in document.paragraphs:
        text = para.text.strip()
        if not text:
            continue
        style = (para.style.name or "").lower() if para.style else ""
        if style.startswith("heading"):
            try:
                level = int(style.split()[-1])
            except (ValueError, IndexError):
                level = 1
            heading_path = heading_path[: level - 1] + [text]
            blocks.append(
                Block(text=text, kind="heading", level=level, heading_path=list(heading_path))
            )
        else:
            blocks.append(Block(text=text, heading_path=list(heading_path)))

    return ParsedDoc(blocks=blocks, mime_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document")
