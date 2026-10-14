<?php

namespace Database\Seeders;

use App\Models\Analysis;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentPage;
use App\Models\ExtractedTerm;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@bayanuk.com'],
            [
                'name' => 'Test User',
                'terms_version' => '1.0',
                'terms_accepted_at' => now(),
            ]
        );

        Document::factory(2)
            ->for($user)
            ->has(
                DocumentPage::factory()->count(2)
                    ->sequence(fn (Sequence $s) => ['page_number' => $s->index + 1]),
                'pages'
            )
            ->has(
                DocumentChunk::factory()->count(3)
                    ->sequence(fn (Sequence $s) => ['chunk_index' => $s->index]),
                'chunks'
            )
            ->has(
                Analysis::factory()
                    ->has(Finding::factory()->count(2), 'findings')
                    ->has(
                        ExtractedTerm::factory()->count(2)
                            ->sequence(
                                ['key' => 'compensation.basic_salary'],
                                ['key' => 'compensation.housing']
                            ),
                        'extractedTerms'
                    ),
                'analyses'
            )
            ->has(
                ChatSession::factory()
                    ->for($user)
                    ->has(ChatMessage::factory()->count(2), 'messages'),
                'chatSessions'
            )
            ->create();
    }
}
