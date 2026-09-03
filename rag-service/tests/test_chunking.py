import pytest

from app.chunking import auto_select, get_chunker
from app.chunking.registry import list_strategies
from app.parsing import extract
from app.parsing.base import Block, ParsedDoc

PROSE = ("The quick brown fox jumps over the lazy dog. " * 160).strip()


def _doc(text: str, mime: str = "text/plain") -> ParsedDoc:
    return ParsedDoc(blocks=[Block(text=text)], mime_type=mime)


@pytest.mark.parametrize("name", ["recursive", "fixed", "sentence"])
def test_strategy_respects_token_budget(name: str) -> None:
    chunker = get_chunker(name)
    config = chunker.parse_config({})
    budget = getattr(config, "chunk_tokens", getattr(config, "max_tokens", 512))

    chunks = chunker.chunk(_doc(PROSE), config)

    assert len(chunks) >= 2
    assert all(c.token_count <= budget * 1.15 for c in chunks)
    assert [c.index for c in chunks] == list(range(len(chunks)))


def test_recursive_overlap_repeats_content() -> None:
    chunker = get_chunker("recursive")
    config = chunker.parse_config({"chunk_tokens": 64, "overlap_tokens": 16})
    chunks = chunker.chunk(_doc(PROSE), config)
    assert len(chunks) >= 3


def test_fixed_chunker_is_deterministic() -> None:
    chunker = get_chunker("fixed")
    config = chunker.parse_config({"chunk_tokens": 100, "overlap_tokens": 0})
    first = [c.text for c in chunker.chunk(_doc(PROSE), config)]
    second = [c.text for c in chunker.chunk(_doc(PROSE), config)]
    assert first == second


def test_markdown_chunker_tags_heading_path() -> None:
    md = b"# Handbook\n\n## Travel\n\n" + b"Book flights early. " * 30 + b"\n\n## Expenses\n\nSubmit within 30 days.\n"
    doc = extract(md, "handbook.md")
    chunker = get_chunker("markdown")
    chunks = chunker.chunk(doc, chunker.parse_config({"max_tokens": 128}))

    paths = {tuple(c.metadata.get("heading_path", [])) for c in chunks}
    assert ("Handbook", "Travel") in paths
    assert ("Handbook", "Expenses") in paths


def test_unknown_strategy_raises() -> None:
    with pytest.raises(ValueError, match="Unknown chunking strategy"):
        get_chunker("nope")


def test_auto_is_not_directly_constructible() -> None:
    with pytest.raises(ValueError, match="auto_select"):
        get_chunker("auto")


class TestAutoSelect:
    def test_markdown_file_picks_markdown(self) -> None:
        doc = extract(b"# A\n\ntext\n\n## B\n\nmore", "x.md")
        assert auto_select(doc, "x.md") == "markdown"

    def test_short_prose_picks_sentence(self) -> None:
        assert auto_select(_doc("Short note. Another sentence."), "note.txt") == "sentence"

    def test_long_prose_picks_recursive(self) -> None:
        big = _doc("word " * 8000)
        assert auto_select(big, "big.txt") == "recursive"

    def test_csv_picks_sentence(self) -> None:
        doc = extract(b"a,b\n1,2\n", "d.csv")
        assert auto_select(doc, "d.csv") == "sentence"


def test_every_listed_strategy_has_valid_defaults() -> None:
    for strategy in list_strategies():
        if strategy["name"] == "auto":
            continue
        chunker = get_chunker(strategy["name"])
        # defaults must round-trip through the config model
        chunker.parse_config(strategy["defaults"])
