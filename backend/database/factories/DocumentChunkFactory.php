<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentChunkFactory extends Factory
{
    protected $model = DocumentChunk::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'page_number' => 1,
            'chunk_index' => 0,
            'content' => fake()->sentence(15),
            'token_count' => fake()->numberBetween(20, 100),
            'embedding' => array_fill(0, 1536, 0.1),
            'embedding_model' => 'text-embedding-3-small',
        ];
    }
}