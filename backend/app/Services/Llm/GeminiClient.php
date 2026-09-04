<?php

namespace App\Services\Llm;

use App\Exceptions\RagException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini via the raw REST API (no official Google PHP SDK for Gemini).
 * https://ai.google.dev/api/generate-content
 */
class GeminiClient implements LlmClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const MAX_TOKENS = 4096;

    public function complete(string $apiKey, string $model, string $system, array $messages): LlmAnswer
    {
        $contents = array_map(
            fn (array $m) => [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ],
            $messages,
        );

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(60)
                ->post(self::BASE_URL."/models/{$model}:generateContent", [
                    'contents' => $contents,
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'generationConfig' => ['maxOutputTokens' => self::MAX_TOKENS],
                ]);
        } catch (ConnectionException $e) {
            throw new RagException('Could not reach Gemini. Try again in a moment.', previous: $e);
        }

        if ($response->failed()) {
            throw new RagException($this->errorMessage($response));
        }

        $body = $response->json();
        $candidate = $body['candidates'][0] ?? null;
        $parts = $candidate['content']['parts'] ?? [];

        if ($candidate === null || $parts === []) {
            $reason = $candidate['finishReason'] ?? $body['promptFeedback']['blockReason'] ?? 'no answer returned';
            throw new RagException("Gemini did not return an answer ({$reason}).");
        }

        $text = collect($parts)->pluck('text')->filter()->implode('');
        $usage = $body['usageMetadata'] ?? [];

        return new LlmAnswer(
            text: trim($text),
            model: $body['modelVersion'] ?? $model,
            stopReason: $candidate['finishReason'] ?? null,
            inputTokens: $usage['promptTokenCount'] ?? 0,
            outputTokens: $usage['candidatesTokenCount'] ?? 0,
        );
    }

    private function errorMessage(Response $response): string
    {
        $status = $response->status();
        $detail = (string) ($response->json('error.message') ?? $response->body());

        return match (true) {
            $status === 400 && str_contains($detail, 'API key not valid') => 'The Gemini API key on this project is invalid.',
            $status === 401 || $status === 403 => 'The Gemini API key on this project is invalid or lacks access to this model.',
            $status === 429 => 'Gemini rate-limited this request. Try again in a moment.',
            $status >= 500 => 'Gemini is temporarily unavailable. Try again in a moment.',
            default => "Gemini rejected the request: {$detail}",
        };
    }
}
