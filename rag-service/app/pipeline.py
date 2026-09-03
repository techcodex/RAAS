from __future__ import annotations

import logging

import httpx

from app.chunking import AUTO, auto_select, get_chunker
from app.config import get_settings
from app.embeddings import get_embedder
from app.parsing import ExtractionError, extract
from app.schemas import ChunkOut, ProcessRequest, ProcessResponse
from app.vectorstore import QdrantStore
from app.vectorstore.qdrant import UpsertPoint, point_id_for

logger = logging.getLogger(__name__)


class ProcessingError(Exception):
    """A document could not be processed (bad input, no text, too large)."""


def _download(url: str) -> bytes:
    settings = get_settings()
    with httpx.Client(timeout=60, follow_redirects=True) as client:
        response = client.get(url)
        response.raise_for_status()
        content = response.content
    if len(content) > settings.request_max_bytes:
        raise ProcessingError("File exceeds the maximum processable size.")
    return content


def process_document(req: ProcessRequest) -> ProcessResponse:
    settings = get_settings()

    try:
        content = _download(req.download_url)
    except httpx.HTTPError as exc:
        raise ProcessingError(f"Could not fetch the document: {exc}") from exc

    try:
        parsed = extract(content, req.filename, req.mime_type)
    except ExtractionError as exc:
        raise ProcessingError(str(exc)) from exc

    strategy = req.strategy
    if strategy == AUTO:
        strategy = auto_select(parsed, req.filename)

    try:
        embedder = get_embedder(req.embedder)
        chunker = get_chunker(strategy, embedder=embedder)
    except ValueError as exc:
        raise ProcessingError(str(exc)) from exc

    config = chunker.parse_config(req.strategy_config)
    chunks = chunker.chunk(parsed, config)

    if not chunks:
        raise ProcessingError("Chunking produced no content.")
    if len(chunks) > settings.max_chunks_per_document:
        raise ProcessingError(
            f"Document produced {len(chunks)} chunks (limit {settings.max_chunks_per_document}). "
            "Use a larger chunk size."
        )

    vectors = embedder.embed([c.text for c in chunks])

    store = QdrantStore(req.collection)
    store.ensure_collection(embedder.dimension, embedder.distance, embedder.model_id)
    if req.replace:
        store.delete_document(req.document_id)

    payload_base = {
        "document_id": req.document_id,
        "organization_id": req.organization_id,
        "project_id": req.project_id,
        "model_id": embedder.model_id,
    }
    store.upsert([
        UpsertPoint(
            vector=vector,
            point_id=point_id_for(req.document_id, chunk.index),
            payload={**payload_base, "chunk_index": chunk.index, "text": chunk.text, **chunk.metadata},
        )
        for chunk, vector in zip(chunks, vectors, strict=True)
    ])

    logger.info(
        "processed document=%s strategy=%s chunks=%d model=%s",
        req.document_id, strategy, len(chunks), embedder.model_id,
    )

    return ProcessResponse(
        status="ready",
        strategy=strategy,
        model_id=embedder.model_id,
        dimension=embedder.dimension,
        distance=embedder.distance,
        collection=req.collection,
        chunk_count=len(chunks),
        chunks=[
            ChunkOut(index=c.index, text=c.text, token_count=c.token_count, metadata=c.metadata)
            for c in chunks
        ],
    )
