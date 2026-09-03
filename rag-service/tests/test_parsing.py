import pytest

from app.parsing import ExtractionError, extract


def test_extracts_plain_text_paragraphs() -> None:
    doc = extract(b"First para.\n\nSecond para.", "notes.txt")
    assert [b.text for b in doc.blocks] == ["First para.", "Second para."]


def test_extracts_markdown_with_heading_path() -> None:
    md = b"# Guide\n\nIntro text.\n\n## Setup\n\nStep one.\n"
    doc = extract(md, "guide.md")

    headings = [b for b in doc.blocks if b.kind == "heading"]
    assert [h.text for h in headings] == ["Guide", "Setup"]

    setup_body = next(b for b in doc.blocks if b.text == "Step one.")
    assert setup_body.heading_path == ["Guide", "Setup"]


def test_extracts_html_text_only() -> None:
    html = b"<html><body><h1>Title</h1><p>Hello</p><script>ignore()</script></body></html>"
    doc = extract(html, "page.html")
    texts = [b.text for b in doc.blocks]
    assert "Title" in texts
    assert "Hello" in texts
    assert "ignore()" not in " ".join(texts)


def test_extracts_csv_rows_as_key_value_pairs() -> None:
    csv_bytes = b"name,role\nAda,Engineer\nGrace,Admiral\n"
    doc = extract(csv_bytes, "people.csv")
    assert doc.blocks[1].text == "name: Ada; role: Engineer"


def test_rejects_unknown_type() -> None:
    with pytest.raises(ExtractionError):
        extract(b"\x00\x01", "mystery.bin")
