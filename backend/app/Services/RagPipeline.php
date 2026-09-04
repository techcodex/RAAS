<?php

namespace App\Services;

use App\Exceptions\RagException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Str;

/**
 * Answers a question about a project's documents: embed + search (rag-service),
 * assemble a grounded prompt, complete with the project's LLM, persist the turn.
 */
class RagPipeline
{
    private const TOP_K = 6;

    public function __construct(
        private readonly RagClient $rag,
        private readonly LlmClient $llm,
    ) {}

    public function ask(
        Project $project,
        ProjectCredential $credential,
        string $question,
        ?Conversation $conversation,
        ?int $topK = null,
    ): Message {
        if ($project->embedding_model_id === null) {
            throw new RagException('This project has no processed documents yet — process at least one before asking questions.');
        }

        $search = $this->rag->search(
            $project->vectorCollection(),
            $question,
            $topK ?? self::TOP_K,
            $project->embedderConfig(),
        );
        $matches = $search['results'] ?? [];

        $conversation ??= $project->conversations()->create([
            'title' => Str::limit($question, 60),
        ]);

        $history = $conversation->messages()->orderBy('id')->get()
            ->map(fn (Message $m) => ['role' => $m->role, 'content' => $m->content]);

        $conversation->messages()->create(['role' => 'user', 'content' => $question]);

        $answer = $this->llm->complete(
            apiKey: $credential->api_key,
            model: $credential->model,
            system: $this->systemPrompt($matches),
            messages: [...$history->all(), ['role' => 'user', 'content' => $question]],
        );

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $answer->text,
            'citations' => $this->citations($matches),
            'usage' => $answer->toUsageArray(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     */
    private function systemPrompt(array $matches): string
    {
        if ($matches === []) {
            return 'You are a helpful assistant for this project. No relevant excerpts were found in the '.
                "uploaded documents for this question. Tell the user you don't have information on this ".
                'topic in the documents — do not answer from outside knowledge.';
        }

        $excerpts = collect($matches)
            ->values()
            ->map(fn (array $m, int $i) => '['.($i + 1).'] '.$m['text'])
            ->implode("\n\n");

        return <<<PROMPT
            You are a helpful assistant answering questions using ONLY the numbered excerpts below,
            taken from the user's uploaded documents. When you use an excerpt, cite it inline like [1].
            If the excerpts don't contain the answer, say you don't have enough information in the
            provided documents — do not use outside knowledge.

            Excerpts:
            {$excerpts}
            PROMPT;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function citations(array $matches): array
    {
        return collect($matches)->values()->map(fn (array $m) => [
            'document_id' => $m['document_id'],
            'chunk_index' => $m['chunk_index'],
            'score' => $m['score'],
            'excerpt' => Str::limit($m['text'], 280),
        ])->all();
    }
}
