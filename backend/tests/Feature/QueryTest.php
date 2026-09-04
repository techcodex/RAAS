<?php

use App\Exceptions\RagException;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Services\RagPipeline;
use Laravel\Sanctum\Sanctum;

it('requires an api key before a project can be queried', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/query", ['question' => 'Hi?'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Add an LLM API key to this project before asking questions.');
});

it('requires a question', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    ProjectCredential::factory()->for($project)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/query", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');
});

it('returns the assistant answer with citations and a conversation id', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    ProjectCredential::factory()->for($project)->create();
    Sanctum::actingAs($user);

    $this->mock(RagPipeline::class)
        ->shouldReceive('ask')
        ->once()
        ->andReturnUsing(function ($p, $credential, $question, $conversation) use ($project) {
            $conversation ??= $project->conversations()->create(['title' => $question]);

            return $conversation->messages()->create([
                'role' => 'assistant', 'content' => 'The travel policy requires booking early [1].',
                'citations' => [['document_id' => 1, 'chunk_index' => 0, 'score' => 0.9, 'excerpt' => 'Book early']],
                'usage' => ['model' => 'claude-opus-5', 'stop_reason' => 'end_turn', 'input_tokens' => 10, 'output_tokens' => 5],
            ]);
        });

    $response = $this->postJson("/api/v1/projects/{$project->id}/query", ['question' => 'What is the travel policy?']);

    $response->assertCreated()
        ->assertJsonPath('data.role', 'assistant')
        ->assertJsonPath('data.content', 'The travel policy requires booking early [1].')
        ->assertJsonCount(1, 'data.citations')
        ->assertJsonPath('conversation_id', fn ($id) => is_int($id));
});

it('translates a pipeline failure into a 422 with a safe message', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    ProjectCredential::factory()->for($project)->create();
    Sanctum::actingAs($user);

    $this->mock(RagPipeline::class)->shouldReceive('ask')->andThrow(new RagException('Anthropic rate-limited this request. Try again in a moment.'));

    $this->postJson("/api/v1/projects/{$project->id}/query", ['question' => 'Hi?'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Anthropic rate-limited this request. Try again in a moment.');
});

it('rejects a conversation_id belonging to another project', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    ProjectCredential::factory()->for($project)->create();
    $otherConversation = Conversation::factory()->forProject(
        Project::factory()->for($user->currentOrganization)->create()
    )->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/query", [
        'question' => 'Hi?', 'conversation_id' => $otherConversation->id,
    ])->assertNotFound();
});

it('will not let a user query another organization project', function () {
    $foreign = Project::factory()->create(['embedding_model_id' => 'm', 'embedding_dimension' => 384]);
    ProjectCredential::factory()->for($foreign)->create();
    Sanctum::actingAs(createOwner());

    $this->postJson("/api/v1/projects/{$foreign->id}/query", ['question' => 'Hi?'])->assertNotFound();
});
