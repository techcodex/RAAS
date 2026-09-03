from __future__ import annotations

from functools import lru_cache

from app.config import get_settings
from app.embeddings.base import TextEmbedder

# fastembed model name -> embedding dimension
SUPPORTED_MODELS: dict[str, int] = {
    "BAAI/bge-small-en-v1.5": 384,
    "BAAI/bge-base-en-v1.5": 768,
    "sentence-transformers/all-MiniLM-L6-v2": 384,
    "intfloat/multilingual-e5-large": 1024,
}


@lru_cache(maxsize=4)
def _load(model_name: str):
    from fastembed import TextEmbedding

    settings = get_settings()
    return TextEmbedding(model_name=model_name, cache_dir=settings.embedding_cache_dir)


class LocalEmbedder(TextEmbedder):
    """On-box embeddings via fastembed (ONNX). No network, no per-token cost."""

    provider = "local"

    def __init__(self, model: str | None = None) -> None:
        settings = get_settings()
        self.model_id = model or settings.local_embedding_model
        if self.model_id not in SUPPORTED_MODELS:
            raise ValueError(
                f"Unsupported local model '{self.model_id}'. "
                f"Choose one of: {', '.join(SUPPORTED_MODELS)}"
            )
        self.dimension = SUPPORTED_MODELS[self.model_id]

    def embed(self, texts: list[str]) -> list[list[float]]:
        if not texts:
            return []
        model = _load(self.model_id)
        return [vector.tolist() for vector in model.embed(texts)]
