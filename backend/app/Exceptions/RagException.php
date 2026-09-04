<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A user-facing failure in the retrieval/generation pipeline (missing
 * embeddings, bad LLM key, provider error). The message is safe to return
 * to the API caller as-is.
 */
class RagException extends RuntimeException {}
