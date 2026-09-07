<?php

namespace Database\Factories;

use App\Models\AppConversation;
use App\Models\AppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppMessage>
 */
class AppMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_conversation_id' => AppConversation::factory(),
            'role' => 'user',
            'content' => fake()->sentence(),
            'citations' => [],
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => 'assistant']);
    }
}
