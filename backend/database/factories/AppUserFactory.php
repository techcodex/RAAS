<?php

namespace Database\Factories;

use App\Models\AppPublication;
use App\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppUser>
 */
class AppUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_publication_id' => AppPublication::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'access_code' => 'LETMEIN',
            'is_active' => true,
        ];
    }

    public function forPublication(AppPublication $publication): static
    {
        return $this->state(fn () => ['app_publication_id' => $publication->id]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
