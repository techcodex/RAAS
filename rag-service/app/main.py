import json
import logging

from fastapi import Depends, FastAPI, HTTPException, status
from fastapi.responses import StreamingResponse

from app.chunking import list_strategies
from app.config import get_settings
from app.embeddings import get_embedder, list_embedders
from app.pipeline import ProcessingError, process_document
from app.schemas import (
    EmbedQueryRequest,
    EmbedQueryResponse,
    ExportRequest,
    ProcessRequest,
    ProcessResponse,
    PurgeDocumentRequest,
    SearchRequest,
    SearchResponse,
    SearchResultOut,
)
from app.security import verify_signature
from app.vectorstore import CollectionMismatch, QdrantStore

settings = get_settings()
logging.basicConfig(level=settings.log_level)

app = FastAPI(title="RAAS RAG Service", version="0.2.0")

Internal = Depends(verify_signature)


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "rag-service", "env": settings.env}


@app.get("/strategies")
def strategies() -> dict:
    return {"strategies": list_strategies(), "embedders": list_embedders()}


@app.post("/process", response_model=ProcessResponse, dependencies=[Internal])
def process(req: ProcessRequest) -> ProcessResponse:
    try:
        return process_document(req)
    except ProcessingError as exc:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, str(exc)) from exc
    except CollectionMismatch as exc:
        raise HTTPException(status.HTTP_409_CONFLICT, str(exc)) from exc


@app.post("/embed-query", response_model=EmbedQueryResponse, dependencies=[Internal])
def embed_query(req: EmbedQueryRequest) -> EmbedQueryResponse:
    try:
        embedder = get_embedder(req.embedder)
    except ValueError as exc:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, str(exc)) from exc
    return EmbedQueryResponse(
        vector=embedder.embed_one(req.text),
        model_id=embedder.model_id,
        dimension=embedder.dimension,
    )


@app.post("/export", dependencies=[Internal])
def export(req: ExportRequest) -> StreamingResponse:
    store = QdrantStore(req.collection)
    if not store.client.collection_exists(req.collection):
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Collection does not exist.")

    info = store.client.get_collection(req.collection)
    manifest = {
        "type": "raas.embeddings.manifest",
        "collection": req.collection,
        "dimension": info.config.params.vectors.size,  # type: ignore[union-attr]
        "distance": str(info.config.params.vectors.distance),  # type: ignore[union-attr]
        "point_count": store.count(),
    }

    def stream():
        yield json.dumps(manifest) + "\n"
        for point in store.scroll_all():
            yield json.dumps(point) + "\n"

    return StreamingResponse(
        stream(),
        media_type="application/x-ndjson",
        headers={"Content-Disposition": f'attachment; filename="{req.collection}.ndjson"'},
    )


@app.post("/collections/drop", dependencies=[Internal])
def drop_collection(req: ExportRequest) -> dict[str, str]:
    QdrantStore(req.collection).drop()
    return {"status": "dropped", "collection": req.collection}


_CORE_PAYLOAD_KEYS = {"document_id", "organization_id", "project_id", "chunk_index", "text", "model_id"}


@app.post("/search", response_model=SearchResponse, dependencies=[Internal])
def search(req: SearchRequest) -> SearchResponse:
    store = QdrantStore(req.collection)
    if not store.client.collection_exists(req.collection):
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Collection does not exist.")

    try:
        embedder = get_embedder(req.embedder)
    except ValueError as exc:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, str(exc)) from exc

    vector = embedder.embed_one(req.query)
    hits = store.search(vector, limit=req.top_k, flt=req.filter)

    results = []
    for hit in hits:
        payload = hit.payload or {}
        results.append(
            SearchResultOut(
                id=str(hit.id),
                score=hit.score,
                document_id=payload["document_id"],
                chunk_index=payload["chunk_index"],
                text=payload["text"],
                metadata={k: v for k, v in payload.items() if k not in _CORE_PAYLOAD_KEYS},
            )
        )
    return SearchResponse(results=results, model_id=embedder.model_id, dimension=embedder.dimension)


@app.post("/documents/purge", dependencies=[Internal])
def purge_document(req: PurgeDocumentRequest) -> dict[str, str]:
    store = QdrantStore(req.collection)
    if not store.client.collection_exists(req.collection):
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Collection does not exist.")
    store.delete_document(req.document_id)
    return {"status": "purged", "collection": req.collection, "document_id": str(req.document_id)}
