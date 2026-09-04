<?php

namespace App\Services\Llm;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Exceptions\RagException;

class AnthropicClient implements LlmClient
{
    private const MAX_TOKENS = 4096;

    public function complete(string $apiKey, string $model, string $system, array $messages): LlmAnswer
    {
        $client = new Client(apiKey: $apiKey);

        try {
            $response = $client->messages->create(
                model: $model,
                maxTokens: self::MAX_TOKENS,
                system: $system,
                messages: $messages,
            );
        } catch (AuthenticationException $e) {
            throw new RagException('The Anthropic API key on this project is invalid or has been revoked.', previous: $e);
        } catch (RateLimitException $e) {
            throw new RagException('Anthropic rate-limited this request. Try again in a moment.', previous: $e);
        } catch (APIConnectionException $e) {
            throw new RagException('Could not reach Anthropic. Try again in a moment.', previous: $e);
        } catch (APIStatusException $e) {
            throw new RagException("Anthropic rejected the request: {$e->getMessage()}", previous: $e);
        }

        if ($response->stopReason === 'refusal') {
            throw new RagException('Anthropic declined to answer this question.');
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new LlmAnswer(
            text: trim($text),
            model: $response->model,
            stopReason: $response->stopReason,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
        );
    }
}
