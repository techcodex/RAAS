<?php

use App\Exceptions\RagException;
use App\Services\Llm\GeminiClient;
use App\Services\Llm\LlmAnswer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function askGemini(array $messages = [['role' => 'user', 'content' => 'What is the travel policy?']]): LlmAnswer
{
    return (new GeminiClient)->complete('fake-key', 'gemini-3.8-flash', 'Answer using the context.', $messages);
}

it('sends contents with roles mapped and the system prompt separate, and parses the answer', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Book 14 days ahead.']], 'role' => 'model'],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 50, 'candidatesTokenCount' => 8, 'totalTokenCount' => 58],
            'modelVersion' => 'gemini-3.8-flash',
        ]),
    ]);

    $answer = askGemini([
        ['role' => 'user', 'content' => 'First question'],
        ['role' => 'assistant', 'content' => 'First answer'],
        ['role' => 'user', 'content' => 'What is the travel policy?'],
    ]);

    expect($answer->text)->toBe('Book 14 days ahead.')
        ->and($answer->model)->toBe('gemini-3.8-flash')
        ->and($answer->stopReason)->toBe('STOP')
        ->and($answer->inputTokens)->toBe(50)
        ->and($answer->outputTokens)->toBe(8);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->hasHeader('x-goog-api-key', 'fake-key')
            && str_contains($request->url(), 'gemini-3.8-flash:generateContent')
            && $body['systemInstruction']['parts'][0]['text'] === 'Answer using the context.'
            && $body['contents'] === [
                ['role' => 'user', 'parts' => [['text' => 'First question']]],
                ['role' => 'model', 'parts' => [['text' => 'First answer']]],
                ['role' => 'user', 'parts' => [['text' => 'What is the travel policy?']]],
            ];
    });
});

it('reports an invalid api key clearly', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            ['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.']], 400
        ),
    ]);

    expect(fn () => askGemini())->toThrow(RagException::class, 'invalid');
});

it('reports a rate limit clearly', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429)]);

    expect(fn () => askGemini())->toThrow(RagException::class, 'rate-limited');
});

it('surfaces a blocked prompt instead of crashing', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [],
            'promptFeedback' => ['blockReason' => 'SAFETY'],
        ]),
    ]);

    expect(fn () => askGemini())->toThrow(RagException::class, 'SAFETY');
});

it('wraps a connection failure', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    expect(fn () => askGemini())->toThrow(RagException::class, 'Could not reach Gemini');
});
