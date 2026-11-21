<?php

use App\Console\Commands\ResumePausedDocuments;
use App\Enums\DocumentStatus;
use App\Jobs\Pipeline\ExtractText;
use App\Models\Document;
use App\Services\AI\BudgetGuard;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

test('it resumes paused documents when daily AI budget is available', function () {
    $doc1 = Document::create([
        'guest_token_hash' => str_repeat('a', 64),
        'original_filename' => 'contract1.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'content1'),
        'storage_key' => 'documents/contract1.pdf',
        'status' => DocumentStatus::EXTRACTING,
        'status_detail' => 'ai_budget_reached',
        'expires_at' => now()->addHours(24),
    ]);

    $doc2 = Document::create([
        'guest_token_hash' => str_repeat('b', 64),
        'original_filename' => 'contract2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'content2'),
        'storage_key' => 'documents/contract2.pdf',
        'status' => DocumentStatus::EXTRACTING,
        'status_detail' => 'ai_budget_reached',
        'expires_at' => now()->addHours(24),
    ]);

    // Active document not paused - must NOT be touched
    $activeDoc = Document::create([
        'guest_token_hash' => str_repeat('c', 64),
        'original_filename' => 'active.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'active'),
        'storage_key' => 'documents/active.pdf',
        'status' => DocumentStatus::EXTRACTING,
        'status_detail' => null,
        'expires_at' => now()->addHours(24),
    ]);

    $mockBudget = Mockery::mock(BudgetGuard::class);
    $mockBudget->shouldReceive('isBudgetReached')->once()->andReturn(false);
    app()->instance(BudgetGuard::class, $mockBudget);

    $this->artisan('documents:resume-paused')
        ->assertSuccessful();

    $doc1->refresh();
    $doc2->refresh();
    $activeDoc->refresh();

    // status_detail should be reset to null
    expect($doc1->status_detail)->toBeNull()
        ->and($doc1->status)->toBe(DocumentStatus::EXTRACTING)
        ->and($doc2->status_detail)->toBeNull()
        ->and($doc2->status)->toBe(DocumentStatus::EXTRACTING)
        ->and($activeDoc->status_detail)->toBeNull();

    // Pipeline jobs must be re-dispatched for the paused documents
    Queue::assertPushed(ExtractText::class, 2);
    Queue::assertPushed(ExtractText::class, function ($job) use ($doc1) {
        return $job->document->id === $doc1->id;
    });
    Queue::assertPushed(ExtractText::class, function ($job) use ($doc2) {
        return $job->document->id === $doc2->id;
    });
});

test('it does not resume paused documents if the daily AI budget is still reached', function () {
    $doc = Document::create([
        'guest_token_hash' => str_repeat('a', 64),
        'original_filename' => 'paused.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'content'),
        'storage_key' => 'documents/paused.pdf',
        'status' => DocumentStatus::EXTRACTING,
        'status_detail' => 'ai_budget_reached',
        'expires_at' => now()->addHours(24),
    ]);

    $mockBudget = Mockery::mock(BudgetGuard::class);
    $mockBudget->shouldReceive('isBudgetReached')->once()->andReturn(true);
    app()->instance(BudgetGuard::class, $mockBudget);

    $this->artisan('documents:resume-paused')
        ->assertSuccessful();

    $doc->refresh();

    // Must stay paused
    expect($doc->status_detail)->toBe('ai_budget_reached');
    Queue::assertNothingPushed();
});

test('the resume job is registered to run daily at 00:00 Asia/Riyadh in the console schedule', function () {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())->filter(function ($event) {
        return str_contains($event->command, 'documents:resume-paused')
            || str_contains($event->command, ResumePausedDocuments::class);
    });

    expect($events)->not->toBeEmpty();

    $event = $events->first();
    expect($event->expression)->toBe('0 0 * * *')
        ->and($event->timezone)->toBe('Asia/Riyadh');
});
