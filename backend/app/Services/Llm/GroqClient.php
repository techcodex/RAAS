<?php

namespace App\Services\Llm;

use App\Exceptions\RagException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Groq via its OpenAI-compatible REST API (no official Groq PHP SDK).
 * https://console.groq.com/docs/api-reference
 */
class GroqClient implements LlmClient
{
    private const BASE_URL = 'https://api.groq.com/openai/v1';

    private const MAX_TOKENS = 4096;

    public function complete(string $apiKey, string $model, string $system, array $messages): LlmAnswer
    {
        $chatMessages = [
            ['role' => 'system', 'content' => $system],
            ...array_map(
                fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
                $messages,
            ),
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post(self::BASE_URL.'/chat/completions', [
                    'model' => $model,
                    'messages' => $chatMessages,
                    'max_tokens' => self::MAX_TOKENS,
                ]);
        } catch (ConnectionException $e) {
            throw new RagException('Could not reach Groq. Try again in a moment.', previous: $e);
        }

        if ($response->failed()) {
            throw new RagException($this->errorMessage($response));
        }

        $body = $response->json();
        $choice = $body['choices'][0] ?? null;
        $text = $choice['message']['content'] ?? null;

        if ($choice === null || $text === null) {
            throw new RagException('Groq did not return an answer.');
        }

        $usage = $body['usage'] ?? [];

        return new LlmAnswer(
            text: trim($text),
            model: $body['model'] ?? $model,
            stopReason: $choice['finish_reason'] ?? null,
            inputTokens: $usage['prompt_tokens'] ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
        );
    }

    private function errorMessage(Response $response): string
    {
        $status = $response->status();
        $detail = (string) ($response->json('error.message') ?? $response->body());

        return match (true) {
            $status === 401 => 'The Groq API key on this project is invalid or has been revoked.',
            $status === 403 => 'The Groq API key on this project lacks access to this model.',
            $status === 404 => "Groq model not found: {$detail}",
            $status === 429 => 'Groq rate-limited this request. Try again in a moment.',
            $status >= 500 => 'Groq is temporarily unavailable. Try again in a moment.',
            default => "Groq rejected the request: {$detail}",
        };
    }
}
