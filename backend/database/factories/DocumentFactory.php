<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(3).'.pdf';

        return [
            'project_id' => Project::factory(),
            'organization_id' => fn (array $attrs) => Project::withoutGlobalScopes()
                ->findOrFail($attrs['project_id'])->organization_id,
            'uploaded_by_user_id' => null,
            'original_filename' => $name,
            'disk' => 's3',
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1_000, 5_000_000),
            'status' => DocumentStatus::Uploaded,
            'error_message' => null,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'organization_id' => $project->organization_id,
        ]);
    }

    public function status(DocumentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
