from __future__ import annotations

from app.embeddings.base import EmbedderConfig, TextEmbedder
from app.embeddings.local import SUPPORTED_MODELS, LocalEmbedder


def get_embedder(config: EmbedderConfig | dict | None = None) -> TextEmbedder:
    resolved: EmbedderConfig
    if config is None:
        resolved = EmbedderConfig()
    elif isinstance(config, dict):
        resolved = EmbedderConfig.model_validate(config)
    else:
        resolved = config

    if resolved.provider == "local":
        return LocalEmbedder(model=resolved.model)

    raise ValueError(
        f"Unknown embedder provider '{resolved.provider}'. Hosted providers (openai, cohere, "
        f"voyage) arrive in Phase 4."
    )


def list_embedders() -> list[dict]:
    return [
        {
            "provider": "local",
            "label": "Local (fastembed / ONNX)",
            "description": "Runs in the rag-service. No API key, no per-token cost.",
            "models": [
                {"id": model, "dimension": dim} for model, dim in SUPPORTED_MODELS.items()
            ],
            "default_model": "BAAI/bge-small-en-v1.5",
        }
    ]
