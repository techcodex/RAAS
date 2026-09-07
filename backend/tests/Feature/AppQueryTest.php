<?php

use App\Enums\OrganizationStatus;
use App\Models\AppConversation;
use App\Models\AppUser;
use App\Services\Llm\LlmAnswer;
use App\Services\RagAnswer;
use App\Services\RagPipeline;
use Laravel\Sanctum\Sanctum;

function fakeRagAnswer(string $text = 'Full-time staff get 20 days [1].'): RagAnswer
{
    return new RagAnswer(
        new LlmAnswer($text, 'model', 'end_turn', 10, 5),
        [['document_id' => 1, 'chunk_index' => 0, 'score' => 0.91, 'excerpt' => 'Leave: 20 days per year']],
    );
}

it('answers a question and starts a conversation', function () {
    $publication = publishedApp();
    $employee = AppUser::factory()->forPublication($publication)->create();
    Sanctum::actingAs($employee);

    $this->mock(RagPipeline::class)->shouldReceive('answer')->once()->andReturn(fakeRagAnswer());

    $response = $this->postJson("/api/app/{$publication->slug}/query", [
        'question' => 'How much leave do I get?',
    ])->assertCreated()
        ->assertJsonPath('data.role', 'assistant')
        ->assertJsonPath('data.content', 'Full-time staff get 20 days [1].')
        ->assertJsonCount(1, 'data.citations');

    $conversationId = $response->json('conversation_id');
    expect($conversationId)->toBeInt();

    $conversation = AppConversation::findOrFail($conversationId);
    expect($conversation->app_user_id)->toBe($employee->id)
        ->and($conversation->messages()->count())->toBe(2)
        ->and($conversation->title)->toBe('How much leave do I get?');
});

it('continues an existing conversation with prior turns as context', function () {
    $publication = publishedApp();
    $employee = AppUser::factory()->forPublication($publication)->create();
    $conversation = AppConversation::factory()->forEmployee($employee)->create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'First question']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'First answer']);
    Sanctum::actingAs($employee);

    $this->mock(RagPipeline::class)->shouldReceive('answer')
        ->withArgs(fn ($project, $credential, $question, $history) => $history === [
            ['role' => 'user', 'content' => 'First question'],
            ['role' => 'assistant', 'content' => 'First answer'],
        ] && $question === 'Second question')
        ->andReturn(fakeRagAnswer('Second answer'));

    $this->postJson("/api/app/{$publication->slug}/query", [
        'question' => 'Second question',
        'conversation_id' => $conversation->id,
    ])->assertCreated();

    expect($conversation->refresh()->messages()->count())->toBe(4);
});

it('lists only the signed-in employee\'s conversations, newest first', function () {
    $publication = publishedApp();
    $me = AppUser::factory()->forPublication($publication)->create();
    $other = AppUser::factory()->forPublication($publication)->create();

    AppConversation::factory()->forEmployee($me)->create(['title' => 'Old', 'updated_at' => now()->subDay()]);
    AppConversation::factory()->forEmployee($me)->create(['title' => 'New']);
    AppConversation::factory()->forEmployee($other)->create(['title' => 'Not mine']);

    Sanctum::actingAs($me);

    $this->getJson("/api/app/{$publication->slug}/conversations")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'New')
        ->assertJsonPath('data.1.title', 'Old');
});

it('returns a conversation with its full message history', function () {
    $publication = publishedApp();
    $employee = AppUser::factory()->forPublication($publication)->create();
    $conversation = AppConversation::factory()->forEmployee($employee)->create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'Q1']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'A1']);
    Sanctum::actingAs($employee);

    $this->getJson("/api/app/{$publication->slug}/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.0.content', 'Q1')
        ->assertJsonPath('data.messages.1.content', 'A1');
});

it('404s another employee\'s conversation', function () {
    $publication = publishedApp();
    $me = AppUser::factory()->forPublication($publication)->create();
    $theirs = AppConversation::factory()->create();
    Sanctum::actingAs($me);

    $this->getJson("/api/app/{$publication->slug}/conversations/{$theirs->id}")->assertNotFound();
});

it('enforces the daily query limit', function () {
    $publication = publishedApp(null, ['daily_query_limit' => 1]);
    $employee = AppUser::factory()->forPublication($publication)->create();
    $conversation = AppConversation::factory()->forEmployee($employee)->create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'Already asked today']);
    Sanctum::actingAs($employee);

    $this->postJson("/api/app/{$publication->slug}/query", ['question' => 'One more?'])
        ->assertStatus(429);
});

it('blocks an employee token from the platform api', function () {
    $publication = publishedApp();
    $employee = AppUser::factory()->forPublication($publication)->create();
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/projects')->assertForbidden();
    $this->getJson('/api/v1/auth/me')->assertForbidden();
    $this->getJson('/api/v1/admin/organizations')->assertForbidden();
});

it('blocks a deactivated employee from chatting even with a live token', function () {
    $publication = publishedApp();
    $employee = AppUser::factory()->forPublication($publication)->create();
    Sanctum::actingAs($employee);

    $this->getJson("/api/app/{$publication->slug}/conversations")->assertOk();

    $employee->update(['is_active' => false]);

    $this->getJson("/api/app/{$publication->slug}/conversations")->assertForbidden();
    $this->postJson("/api/app/{$publication->slug}/query", ['question' => 'Hi'])->assertForbidden();
});

it('blocks a platform user token from the employee query routes', function () {
    $publication = publishedApp();
    Sanctum::actingAs(createOwner());

    $this->postJson("/api/app/{$publication->slug}/query", ['question' => 'Hi'])->assertForbidden();
    $this->getJson("/api/app/{$publication->slug}/conversations")->assertForbidden();
});

it('blocks queries once the owning organization is disabled', function () {
    $project = publishableProject();
    $publication = publishedApp($project);
    $employee = AppUser::factory()->forPublication($publication)->create();
    Sanctum::actingAs($employee);

    $project->organization->forceFill(['status' => OrganizationStatus::Disabled])->save();

    $this->postJson("/api/app/{$publication->slug}/query", ['question' => 'Hi'])->assertNotFound();
});
