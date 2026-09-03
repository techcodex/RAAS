<?php

namespace Database\Factories;

use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = fake()->paragraph();

        return [
            'document_id' => Document::factory(),
            'organization_id' => fn (array $attrs) => Document::withoutGlobalScopes()
                ->findOrFail($attrs['document_id'])->organization_id,
            'chunk_index' => 0,
            'content' => $content,
            'token_count' => (int) ceil(mb_strlen($content) / 4),
            'metadata' => [],
        ];
    }
}
