from __future__ import annotations

from pydantic import BaseModel, Field

from app.embeddings.base import EmbedderConfig


class ProcessRequest(BaseModel):
    document_id: int
    organization_id: int
    project_id: int
    collection: str
    filename: str
    mime_type: str | None = None
    download_url: str
    strategy: str = "auto"
    strategy_config: dict | None = None
    embedder: EmbedderConfig | None = None
    replace: bool = True


class ChunkOut(BaseModel):
    index: int
    text: str
    token_count: int
    metadata: dict = Field(default_factory=dict)


class ProcessResponse(BaseModel):
    status: str
    strategy: str
    model_id: str
    dimension: int
    distance: str
    collection: str
    chunk_count: int
    chunks: list[ChunkOut]


class ChunkInput(BaseModel):
    index: int
    text: str
    metadata: dict = Field(default_factory=dict)


class EmbedDocumentRequest(BaseModel):
    """Re-embed a document's already-extracted chunks (no download / parse / chunk)."""

    collection: str
    organization_id: int
    project_id: int
    document_id: int
    embedder: EmbedderConfig | None = None
    chunks: list[ChunkInput]
    replace: bool = True


class EmbedDocumentResponse(BaseModel):
    model_id: str
    dimension: int
    distance: str
    collection: str
    chunk_count: int


class EmbedQueryRequest(BaseModel):
    text: str
    embedder: EmbedderConfig | None = None


class EmbedQueryResponse(BaseModel):
    vector: list[float]
    model_id: str
    dimension: int


class ExportRequest(BaseModel):
    collection: str


class PurgeDocumentRequest(BaseModel):
    collection: str
    document_id: int


class SearchRequest(BaseModel):
    collection: str
    query: str
    top_k: int = 6
    embedder: EmbedderConfig | None = None
    filter: dict[str, int | str] | None = None


class SearchResultOut(BaseModel):
    id: str
    score: float
    document_id: int
    chunk_index: int
    text: str
    metadata: dict = Field(default_factory=dict)


class SearchResponse(BaseModel):
    results: list[SearchResultOut]
    model_id: str
    dimension: int
