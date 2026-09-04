<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;

it('lists a project conversations newest first', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    $old = Conversation::factory()->forProject($project)->create(['updated_at' => now()->subDay()]);
    $new = Conversation::factory()->forProject($project)->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$project->id}/conversations")
        ->assertOk()
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
});

it('shows a conversation with its messages in order', function () {
    $user = createOwner();
    $conversation = Conversation::factory()->forProject(
        Project::factory()->for($user->currentOrganization)->create()
    )->create();
    Message::factory()->forConversation($conversation)->create(['content' => 'Question one']);
    Message::factory()->forConversation($conversation)->assistant()->create(['content' => 'Answer one']);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.0.content', 'Question one')
        ->assertJsonPath('data.messages.1.role', 'assistant');
});

it('deletes a conversation and its messages', function () {
    $user = createOwner();
    $conversation = Conversation::factory()->forProject(
        Project::factory()->for($user->currentOrganization)->create()
    )->create();
    Message::factory()->forConversation($conversation)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/conversations/{$conversation->id}")->assertNoContent();

    $this->assertModelMissing($conversation);
    expect(Message::count())->toBe(0);
});

it('does not expose another organization conversation', function () {
    $foreign = Conversation::factory()->forProject(Project::factory()->create())->create();
    Sanctum::actingAs(createOwner());

    $this->getJson("/api/v1/conversations/{$foreign->id}")->assertNotFound();
    $this->deleteJson("/api/v1/conversations/{$foreign->id}")->assertNotFound();
});
