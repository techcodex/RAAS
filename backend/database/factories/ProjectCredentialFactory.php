<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectCredential>
 */
class ProjectCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'organization_id' => fn (array $attrs) => Project::withoutGlobalScopes()
                ->findOrFail($attrs['project_id'])->organization_id,
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-test-'.fake()->uuid(),
            'model' => 'claude-opus-5',
        ];
    }
}
