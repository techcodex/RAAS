<?php

namespace Database\Factories;

use App\Models\AppConversation;
use App\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppConversation>
 */
class AppConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = AppUser::factory();

        return [
            'app_user_id' => $user,
            'app_publication_id' => fn (array $attrs) => AppUser::findOrFail($attrs['app_user_id'])->app_publication_id,
            'title' => fake()->sentence(),
        ];
    }

    /**
     * Bind the conversation to an existing employee (and their publication).
     */
    public function forEmployee(AppUser $user): static
    {
        return $this->state(fn () => [
            'app_user_id' => $user->id,
            'app_publication_id' => $user->app_publication_id,
        ]);
    }
}
