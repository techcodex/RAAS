<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'organization_id' => fn (array $attrs) => Conversation::withoutGlobalScopes()
                ->findOrFail($attrs['conversation_id'])->organization_id,
            'role' => 'user',
            'content' => fake()->sentence(),
            'citations' => null,
            'usage' => null,
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state(fn () => [
            'conversation_id' => $conversation->id,
            'organization_id' => $conversation->organization_id,
        ]);
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => 'assistant']);
    }
}
