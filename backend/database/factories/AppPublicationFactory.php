<?php

namespace Database\Factories;

use App\Models\AppPublication;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppPublication>
 */
class AppPublicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->catchPhrase();

        return [
            'project_id' => Project::factory(),
            'organization_id' => fn (array $attrs) => Project::withoutGlobalScopes()
                ->findOrFail($attrs['project_id'])->organization_id,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
            'welcome_message' => fake()->sentence(),
            'suggested_questions' => [fake()->sentence().'?', fake()->sentence().'?'],
            'daily_query_limit' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Bind the publication (and its organization) to an existing project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'organization_id' => $project->organization_id,
        ]);
    }
}
