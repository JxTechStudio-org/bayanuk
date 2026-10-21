<?php

use App\Models\Analysis;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('deletes all data linked to a document (cascade)', function () {
    $document = Document::factory()->create();
    DocumentPage::factory()->create(['document_id' => $document->id]);
    DocumentChunk::factory()->create(['document_id' => $document->id]);
    $analysis = Analysis::factory()->create(['document_id' => $document->id]);

    $sessionId = (string) Str::uuid();

    DB::table('extracted_terms')->insert([
        'id' => (string) Str::uuid(),
        'analysis_id' => $analysis->id,
        'key' => 'compensation.basic_salary',
        'found' => true,
    ]);
    DB::table('findings')->insert([
        'id' => (string) Str::uuid(),
        'analysis_id' => $analysis->id,
        'category' => 'non_compete',
        'severity' => 'attention',
        'source' => 'rule',
        'title' => 'Non-compete clause',
        'explanation' => 'Why it matters.',
    ]);
    DB::table('analysis_feedback')->insert([
        'id' => (string) Str::uuid(),
        'analysis_id' => $analysis->id,
        'rating' => 1,
        'created_at' => now(),
    ]);
    DB::table('chat_sessions')->insert([
        'id' => $sessionId,
        'document_id' => $document->id,
        'created_at' => now(),
    ]);
    DB::table('chat_messages')->insert([
        'id' => (string) Str::uuid(),
        'chat_session_id' => $sessionId,
        'role' => 'user',
        'content' => 'What is my notice period?',
        'created_at' => now(),
    ]);

    $tables = [
        'document_pages', 'document_chunks', 'analyses', 'extracted_terms',
        'findings', 'analysis_feedback', 'chat_sessions', 'chat_messages',
    ];

    foreach ($tables as $table) {
        expect(DB::table($table)->count())->toBe(1);
    }

    $document->delete();

    expect(Document::count())->toBe(0);

    foreach ($tables as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
});

// TODO: Moved to Document Upload & Access API task (will be logged via Action/Service explicitly with reasons: user_request, retention_expired, account_deleted)
// it('creates an audit log when a document is deleted', function () {
//     $document = Document::factory()->create();

//     $document->delete();

//     expect(DB::table('deletion_logs')->count())->toBe(1);
//     expect(DB::table('deletion_logs')->first()->document_id)->toBe($document->id);
// });
