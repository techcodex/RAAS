from __future__ import annotations

import abc
from dataclasses import dataclass

from pydantic import BaseModel, Field


class EmbedderConfig(BaseModel):
    model_config = {"extra": "forbid"}

    provider: str = Field(default="local", description="Embedder provider id")
    model: str | None = Field(default=None, description="Model name (defaults to the provider's default)")


@dataclass
class Embedding:
    vectors: list[list[float]]
    model_id: str
    dimension: int
    distance: str = "Cosine"


class TextEmbedder(abc.ABC):
    """Turns text into dense vectors. One concrete subclass per provider."""

    provider: str
    model_id: str
    dimension: int
    distance: str = "Cosine"

    @abc.abstractmethod
    def embed(self, texts: list[str]) -> list[list[float]]:
        ...

    def embed_one(self, text: str) -> list[float]:
        return self.embed([text])[0]

    def as_result(self, texts: list[str]) -> Embedding:
        return Embedding(
            vectors=self.embed(texts),
            model_id=self.model_id,
            dimension=self.dimension,
            distance=self.distance,
        )
