<?php

use App\Exceptions\RagException;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Models\User;
use App\Services\Llm\LlmAnswer;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmClientResolver;
use App\Services\RagClient;
use App\Services\RagPipeline;
use App\Support\TenantContext;

/**
 * RagPipeline is normally reached through the `tenant` middleware, which binds
 * TenantContext before any tenant-scoped query runs. These tests call it
 * directly, so bind the tenant explicitly — matching what ProcessDocument
 * (the other tenant-scoped background caller) does in its own handle().
 */
function bindTenant(User $owner): void
{
    app(TenantContext::class)->set($owner->currentOrganization);
}

/**
 * Stub the provider resolver to hand back a given LlmClient double, regardless
 * of which provider the credential asks for — the pipeline tests aren't about
 * provider selection (that's LlmClientResolverTest).
 */
function fakeLlmClient(LlmClient $client): void
{
    $resolver = Mockery::mock(LlmClientResolver::class);
    $resolver->shouldReceive('for')->andReturn($client);
    app()->instance(LlmClientResolver::class, $resolver);
}

function fakeSearchResult(array $overrides = []): array
{
    return array_merge([
        'results' => [
            ['id' => 'a', 'score' => 0.91, 'document_id' => 1, 'chunk_index' => 0, 'text' => 'Book travel 14 days ahead.'],
            ['id' => 'b', 'score' => 0.85, 'document_id' => 1, 'chunk_index' => 1, 'text' => 'Economy class under six hours.'],
        ],
        'model_id' => 'BAAI/bge-small-en-v1.5',
        'dimension' => 384,
    ], $overrides);
}

it('refuses to answer when the project has no embeddings yet', function () {
    $owner = createOwner();
    bindTenant($owner);
    $project = Project::factory()->for($owner->currentOrganization)->create(['embedding_model_id' => null]);
    $credential = ProjectCredential::factory()->for($project)->create();

    $pipeline = app(RagPipeline::class);

    expect(fn () => $pipeline->ask($project, $credential, 'What is the travel policy?', null))
        ->toThrow(RagException::class, 'no processed documents');
});

it('searches, calls the llm with grounded context, and persists both turns', function () {
    $owner = createOwner();
    bindTenant($owner);
    $project = Project::factory()->for($owner->currentOrganization)->create([
        'embedding_model_id' => 'BAAI/bge-small-en-v1.5',
        'embedding_dimension' => 384,
    ]);
    $credential = ProjectCredential::factory()->for($project)->create(['provider' => 'anthropic', 'model' => 'claude-opus-5']);

    $this->mock(RagClient::class)
        ->shouldReceive('search')
        ->once()
        ->with($project->vectorCollection(), 'What is the travel policy?', 6, $project->embedderConfig())
        ->andReturn(fakeSearchResult());

    $llmClient = Mockery::mock(LlmClient::class);
    $llmClient->shouldReceive('complete')
        ->once()
        ->withArgs(function (string $apiKey, string $model, string $system, array $messages) use ($credential) {
            return $apiKey === $credential->api_key
                && $model === 'claude-opus-5'
                && str_contains($system, 'Book travel 14 days ahead.')
                && str_contains($system, '[1]')
                && end($messages) === ['role' => 'user', 'content' => 'What is the travel policy?'];
        })
        ->andReturn(new LlmAnswer('Book at least 14 days ahead [1].', 'claude-opus-5', 'end_turn', 120, 12));
    $resolver = Mockery::mock(LlmClientResolver::class);
    $resolver->shouldReceive('for')->once()->with('anthropic')->andReturn($llmClient);
    app()->instance(LlmClientResolver::class, $resolver);

    $message = app(RagPipeline::class)->ask($project, $credential, 'What is the travel policy?', null);

    expect($message->role)->toBe('assistant')
        ->and($message->content)->toBe('Book at least 14 days ahead [1].')
        ->and($message->citations)->toHaveCount(2)
        ->and($message->citations[0]['document_id'])->toBe(1)
        ->and($message->usage)->toBe([
            'model' => 'claude-opus-5', 'stop_reason' => 'end_turn', 'input_tokens' => 120, 'output_tokens' => 12,
        ]);

    $conversation = Conversation::firstOrFail();
    expect($conversation->messages()->count())->toBe(2)
        ->and($conversation->messages()->first()->role)->toBe('user')
        ->and($conversation->title)->toBe('What is the travel policy?');
});

it('continues an existing conversation with prior turns as history', function () {
    $owner = createOwner();
    bindTenant($owner);
    $project = Project::factory()->for($owner->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    $credential = ProjectCredential::factory()->for($project)->create();
    $conversation = Conversation::factory()->forProject($project)->create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'First question']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'First answer']);

    $this->mock(RagClient::class)->shouldReceive('search')->andReturn(fakeSearchResult());

    $llmClient = Mockery::mock(LlmClient::class);
    $llmClient->shouldReceive('complete')
        ->withArgs(fn (string $k, string $m, string $s, array $messages) => $messages === [
            ['role' => 'user', 'content' => 'First question'],
            ['role' => 'assistant', 'content' => 'First answer'],
            ['role' => 'user', 'content' => 'Second question'],
        ])
        ->andReturn(new LlmAnswer('Second answer', 'claude-opus-5', 'end_turn', 10, 5));
    fakeLlmClient($llmClient);

    app(RagPipeline::class)->ask($project, $credential, 'Second question', $conversation);

    expect($conversation->refresh()->messages()->count())->toBe(4);
});

it('answers "no information" when nothing relevant is found, without inventing an answer', function () {
    $owner = createOwner();
    bindTenant($owner);
    $project = Project::factory()->for($owner->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    $credential = ProjectCredential::factory()->for($project)->create();

    $this->mock(RagClient::class)->shouldReceive('search')->andReturn(['results' => [], 'model_id' => 'm', 'dimension' => 384]);

    $llmClient = Mockery::mock(LlmClient::class);
    $llmClient->shouldReceive('complete')
        ->withArgs(fn (string $k, string $m, string $system) => str_contains($system, "don't have information"))
        ->andReturn(new LlmAnswer("I don't have information on that in the uploaded documents.", 'claude-opus-5', 'end_turn', 40, 15));
    fakeLlmClient($llmClient);

    $message = app(RagPipeline::class)->ask($project, $credential, 'Unrelated question', null);

    expect($message->citations)->toBe([]);
});

it('surfaces a bad api key as a RagException', function () {
    $owner = createOwner();
    bindTenant($owner);
    $project = Project::factory()->for($owner->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    $credential = ProjectCredential::factory()->for($project)->create();

    $this->mock(RagClient::class)->shouldReceive('search')->andReturn(fakeSearchResult());

    $llmClient = Mockery::mock(LlmClient::class);
    $llmClient->shouldReceive('complete')->andThrow(new RagException('The Anthropic API key on this project is invalid or has been revoked.'));
    fakeLlmClient($llmClient);

    expect(fn () => app(RagPipeline::class)->ask($project, $credential, 'Q', null))
        ->toThrow(RagException::class, 'invalid or has been revoked');
});

it('resolves the llm client for the credential provider, gemini included', function () {
    $owner = createOwner();
    bindTenant($owner);
    $project = Project::factory()->for($owner->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    $credential = ProjectCredential::factory()->for($project)->create(['provider' => 'gemini', 'model' => 'gemini-3.8-flash']);

    $this->mock(RagClient::class)->shouldReceive('search')->andReturn(fakeSearchResult());

    $llmClient = Mockery::mock(LlmClient::class);
    $llmClient->shouldReceive('complete')
        ->withArgs(fn (string $k, string $model) => $model === 'gemini-3.8-flash')
        ->andReturn(new LlmAnswer('Answer.', 'gemini-3.8-flash', 'STOP', 20, 8));

    $resolver = Mockery::mock(LlmClientResolver::class);
    $resolver->shouldReceive('for')->once()->with('gemini')->andReturn($llmClient);
    app()->instance(LlmClientResolver::class, $resolver);

    $message = app(RagPipeline::class)->ask($project, $credential, 'Q', null);

    expect($message->usage['model'])->toBe('gemini-3.8-flash');
});
