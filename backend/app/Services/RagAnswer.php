<?php

namespace App\Services;

use App\Services\Llm\LlmAnswer;

/**
 * The result of running the RAG pipeline for one question, before persistence:
 * the grounded LLM answer plus the citations that back it.
 */
readonly class RagAnswer
{
    /**
     * @param  list<array<string, mixed>>  $citations
     */
    public function __construct(
        public LlmAnswer $answer,
        public array $citations,
    ) {}
}
