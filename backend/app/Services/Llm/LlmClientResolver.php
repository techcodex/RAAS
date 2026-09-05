<?php

namespace App\Services\Llm;

use App\Exceptions\RagException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves a project credential's provider string to the matching LlmClient.
 * Add a provider by adding one entry here and to App\Support\LlmProviders.
 */
class LlmClientResolver
{
    /**
     * @var array<string, class-string<LlmClient>>
     */
    private const CLIENTS = [
        'anthropic' => AnthropicClient::class,
        'gemini' => GeminiClient::class,
        'groq' => GroqClient::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(string $provider): LlmClient
    {
        $class = self::CLIENTS[$provider]
            ?? throw new RagException("Unsupported LLM provider '{$provider}'.");

        return $this->container->make($class);
    }
}
