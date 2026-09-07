<?php

namespace App\Services;

use App\Enums\ReembedStatus;
use App\Exceptions\RagException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Services\Llm\LlmClientResolver;
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
        private readonly LlmClientResolver $llmResolver,
    ) {}

    /**
     * Run retrieval + grounded completion for one question, without persisting
     * anything. Shared by the owner query flow and the employee app.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    public function answer(
        Project $project,
        ProjectCredential $credential,
        string $question,
        array $history = [],
        ?int $topK = null,
    ): RagAnswer {
        if ($project->embedding_model_id === null) {
            throw new RagException('This project has no processed documents yet — process at least one before asking questions.');
        }

        if ($project->reembed_status !== null) {
            throw new RagException($project->reembed_status === ReembedStatus::Failed
                ? 'The last re-embedding of this project failed. Change the embedding model to try again.'
                : 'This project is re-embedding its documents — querying will be available once it finishes.');
        }

        $search = $this->rag->search(
            $project->vectorCollection(),
            $question,
            $topK ?? self::TOP_K,
            $project->embedderConfig(),
        );
        $matches = $search['results'] ?? [];

        $answer = $this->llmResolver->for($credential->provider)->complete(
            apiKey: $credential->api_key,
            model: $credential->model,
            system: $this->systemPrompt($matches),
            messages: [...$history, ['role' => 'user', 'content' => $question]],
        );

        return new RagAnswer($answer, $this->citations($matches));
    }

    /**
     * Owner-side query: answer, then persist both turns to the project's
     * `conversations`/`messages`.
     */
    public function ask(
        Project $project,
        ProjectCredential $credential,
        string $question,
        ?Conversation $conversation,
        ?int $topK = null,
    ): Message {
        $history = $conversation
            ? $conversation->messages()->orderBy('id')->get()
                ->map(fn (Message $m) => ['role' => $m->role, 'content' => $m->content])->all()
            : [];

        $result = $this->answer($project, $credential, $question, $history, $topK);

        $conversation ??= $project->conversations()->create([
            'title' => Str::limit($question, 60),
        ]);
        $conversation->messages()->create(['role' => 'user', 'content' => $question]);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result->answer->text,
            'citations' => $result->citations,
            'usage' => $result->answer->toUsageArray(),
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
