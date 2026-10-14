<?php

namespace Database\Factories;

use App\Enums\DocumentLanguage;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'guest_token_hash' => null,
            'original_filename' => 'employment_contract_'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(50000, 5000000),
            'sha256' => hash('sha256', fake()->unique()->text()),
            'storage_key' => 'documents/'.Str::uuid().'.pdf',
            'page_count' => fake()->numberBetween(1, 10),
            'language' => DocumentLanguage::ARABIC->value,
            'document_type' => DocumentType::EMPLOYMENT_CONTRACT->value,
            'status' => DocumentStatus::READY->value,
            'status_detail' => null,
            'is_scanned' => false,
            'processing_started_at' => now()->subMinutes(2),
            'processing_finished_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function asGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_token_hash' => hash('sha256', Str::random(32)),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::ANALYZING->value,
            'processing_finished_at' => null,
        ]);
    }
}
