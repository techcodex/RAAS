from __future__ import annotations

import uuid
from dataclasses import dataclass
from functools import lru_cache

from app.config import get_settings


class CollectionMismatch(Exception):
    """The target collection was built with a different embedding model or dimension."""


@dataclass
class UpsertPoint:
    vector: list[float]
    payload: dict
    point_id: str


@lru_cache
def _client():
    from qdrant_client import QdrantClient

    return QdrantClient(url=get_settings().qdrant_url)


def point_id_for(document_id: int, chunk_index: int) -> str:
    return str(uuid.uuid5(uuid.NAMESPACE_URL, f"doc:{document_id}:chunk:{chunk_index}"))


class QdrantStore:
    """Thin wrapper over qdrant-client. One collection per RAAS project."""

    def __init__(self, collection: str) -> None:
        self.collection = collection
        self.client = _client()

    def ensure_collection(self, dimension: int, distance: str, model_id: str) -> None:
        from qdrant_client import models

        if self.client.collection_exists(self.collection):
            info = self.client.get_collection(self.collection)
            existing_dim = info.config.params.vectors.size  # type: ignore[union-attr]
            if existing_dim != dimension:
                raise CollectionMismatch(
                    f"Collection '{self.collection}' has dimension {existing_dim}, "
                    f"incompatible with model '{model_id}' ({dimension})."
                )
            return

        self.client.create_collection(
            collection_name=self.collection,
            vectors_config=models.VectorParams(
                size=dimension, distance=models.Distance[distance.upper()]
            ),
        )
        # The embedder <-> collection binding (model_id, dimension) is recorded on the
        # project row in Postgres; here we just index the fields we filter on.
        for field in ("document_id", "organization_id", "project_id"):
            self.client.create_payload_index(
                self.collection, field_name=field, field_schema=models.PayloadSchemaType.INTEGER
            )

    def delete_document(self, document_id: int) -> None:
        from qdrant_client import models

        self.client.delete(
            collection_name=self.collection,
            points_selector=models.FilterSelector(
                filter=models.Filter(
                    must=[models.FieldCondition(key="document_id", match=models.MatchValue(value=document_id))]
                )
            ),
            wait=True,
        )

    def upsert(self, points: list[UpsertPoint]) -> None:
        from qdrant_client import models

        if not points:
            return
        self.client.upsert(
            collection_name=self.collection,
            points=[
                models.PointStruct(id=p.point_id, vector=p.vector, payload=p.payload)
                for p in points
            ],
            wait=True,
        )

    def count(self) -> int:
        return self.client.count(self.collection, exact=True).count

    def scroll_all(self, batch: int = 256):
        """Yield every point (id, vector, payload) — used by the export endpoint."""
        offset = None
        while True:
            records, offset = self.client.scroll(
                collection_name=self.collection,
                with_payload=True,
                with_vectors=True,
                limit=batch,
                offset=offset,
            )
            for record in records:
                yield {"id": record.id, "vector": record.vector, "payload": record.payload}
            if offset is None:
                break

    def drop(self) -> None:
        if self.client.collection_exists(self.collection):
            self.client.delete_collection(self.collection)
