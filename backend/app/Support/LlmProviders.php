<?php

namespace App\Support;

/**
 * The LLM providers and models a project credential may select. Single source
 * of truth for validation (StoreCredentialRequest) and defaulting
 * (ProjectCredentialController).
 */
class LlmProviders
{
    /**
     * @var array<string, list<string>>
     */
    public const MODELS = [
        'anthropic' => ['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5'],
        // Flash-class only — Gemini's no-credit-card free tier does not include Pro models.
        'gemini' => ['gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-2.0-flash'],
        'groq' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'openai/gpt-oss-20b'],
    ];

    /**
     * @var array<string, string>
     */
    public const DEFAULT_MODEL = [
        'anthropic' => 'claude-opus-5',
        'gemini' => 'gemini-3.8-flash',
        'groq' => 'llama-3.3-70b-versatile',
    ];

    /**
     * @return list<string>
     */
    public static function providers(): array
    {
        return array_keys(self::MODELS);
    }

    /**
     * @return list<string>
     */
    public static function allModels(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::MODELS))));
    }

    /**
     * @return list<string>
     */
    public static function modelsFor(string $provider): array
    {
        return self::MODELS[$provider] ?? [];
    }

    public static function defaultModelFor(string $provider): ?string
    {
        return self::DEFAULT_MODEL[$provider] ?? null;
    }

    public static function supportsModel(string $provider, string $model): bool
    {
        return in_array($model, self::modelsFor($provider), true);
    }
}
