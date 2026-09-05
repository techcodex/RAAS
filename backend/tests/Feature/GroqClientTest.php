<?php

use App\Exceptions\RagException;
use App\Services\Llm\GroqClient;
use App\Services\Llm\LlmAnswer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function askGroq(array $messages = [['role' => 'user', 'content' => 'What is the travel policy?']]): LlmAnswer
{
    return (new GroqClient)->complete('fake-key', 'llama-3.3-70b-versatile', 'Answer using the context.', $messages);
}

it('sends the system prompt as a leading message and parses the answer', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'model' => 'llama-3.3-70b-versatile',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Book 14 days ahead.'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 8, 'total_tokens' => 58],
        ]),
    ]);

    $answer = askGroq([
        ['role' => 'user', 'content' => 'First question'],
        ['role' => 'assistant', 'content' => 'First answer'],
        ['role' => 'user', 'content' => 'What is the travel policy?'],
    ]);

    expect($answer->text)->toBe('Book 14 days ahead.')
        ->and($answer->model)->toBe('llama-3.3-70b-versatile')
        ->and($answer->stopReason)->toBe('stop')
        ->and($answer->inputTokens)->toBe(50)
        ->and($answer->outputTokens)->toBe(8);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->hasHeader('Authorization', 'Bearer fake-key')
            && str_contains($request->url(), '/chat/completions')
            && $body['model'] === 'llama-3.3-70b-versatile'
            && $body['messages'] === [
                ['role' => 'system', 'content' => 'Answer using the context.'],
                ['role' => 'user', 'content' => 'First question'],
                ['role' => 'assistant', 'content' => 'First answer'],
                ['role' => 'user', 'content' => 'What is the travel policy?'],
            ];
    });
});

it('reports an invalid api key clearly', function () {
    Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);

    expect(fn () => askGroq())->toThrow(RagException::class, 'invalid or has been revoked');
});

it('reports a rate limit clearly', function () {
    Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'rate limit reached']], 429)]);

    expect(fn () => askGroq())->toThrow(RagException::class, 'rate-limited');
});

it('reports an unknown model clearly', function () {
    Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => "model 'x' not found"]], 404)]);

    expect(fn () => askGroq())->toThrow(RagException::class, 'model not found');
});

it('wraps a connection failure', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    expect(fn () => askGroq())->toThrow(RagException::class, 'Could not reach Groq');
});
