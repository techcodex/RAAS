<?php

use App\Exceptions\RagException;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\GeminiClient;
use App\Services\Llm\LlmClientResolver;

it('resolves each supported provider to its client', function () {
    $resolver = app(LlmClientResolver::class);

    expect($resolver->for('anthropic'))->toBeInstanceOf(AnthropicClient::class)
        ->and($resolver->for('gemini'))->toBeInstanceOf(GeminiClient::class);
});

it('rejects an unknown provider', function () {
    expect(fn () => app(LlmClientResolver::class)->for('openai'))
        ->toThrow(RagException::class, "Unsupported LLM provider 'openai'");
});
