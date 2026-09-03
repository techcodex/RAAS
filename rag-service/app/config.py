from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_prefix="RAG_", extra="ignore")

    env: str = "local"
    log_level: str = "INFO"

    # Shared HMAC secret for internal Laravel -> rag-service calls.
    internal_secret: str = "change-me"

    # Qdrant.
    qdrant_url: str = "http://qdrant:6333"

    # Default embedder.
    default_embedder: str = "local"
    local_embedding_model: str = "BAAI/bge-small-en-v1.5"

    # Where fastembed caches ONNX model files (Docker overrides to /app/models).
    embedding_cache_dir: str = "models"

    # Guardrails.
    max_chunks_per_document: int = 10_000
    request_max_bytes: int = 100 * 1024 * 1024


@lru_cache
def get_settings() -> Settings:
    return Settings()
