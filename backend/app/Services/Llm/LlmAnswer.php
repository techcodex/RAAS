<?php

namespace App\Services\Llm;

/**
 * A completed LLM response, provider-agnostic.
 */
readonly class LlmAnswer
{
    public function __construct(
        public string $text,
        public string $model,
        public ?string $stopReason,
        public int $inputTokens,
        public int $outputTokens,
    ) {}

    /**
     * @return array{model: string, stop_reason: string|null, input_tokens: int, output_tokens: int}
     */
    public function toUsageArray(): array
    {
        return [
            'model' => $this->model,
            'stop_reason' => $this->stopReason,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
        ];
    }
}
