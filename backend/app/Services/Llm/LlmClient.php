<?php

namespace App\Services\Llm;

use App\Exceptions\RagException;

/**
 * A chat-completion provider. One implementation per provider (Anthropic now;
 * OpenAI/others plug in the same way in a later phase).
 */
interface LlmClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages  Conversation so far, oldest first, ending in the new user question.
     *
     * @throws RagException On an invalid key, rate limit, or other provider failure — message is safe to show the user.
     */
    public function complete(string $apiKey, string $model, string $system, array $messages): LlmAnswer;
}
