<?php

use App\Console\Commands\PurgeExpiredDocuments;
use App\Enums\AnalysisStatus;
use App\Enums\DocumentStatus;
use App\Models\Analysis;
use App\Models\Document;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

test('it purges expired documents: removes storage file first, deletes data and writes deletion log', function () {
    $storageKey = 'documents/expired_file.pdf';
    Storage::disk('s3')->put($storageKey, 'content');
    Storage::disk('s3')->assertExists($storageKey);

    $doc = Document::create([
        'guest_token_hash' => str_repeat('a', 64),
        'original_filename' => 'expired.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'content'),
        'storage_key' => $storageKey,
        'status' => DocumentStatus::READY,
        'expires_at' => now()->subMinutes(10), // expired!
    ]);

    // Attach analysis to verify cascade delete
    $analysis = Analysis::create([
        'document_id' => $doc->id,
        'status' => AnalysisStatus::SUCCEEDED,
        'provider' => 'gemini',
        'model' => 'gemini-3.8-flash',
        'prompt_version' => '1.0',
        'schema_version' => '1.0',
    ]);

    $this->artisan('documents:purge-expired')
        ->assertSuccessful();

    // 1. File must be deleted from storage
    Storage::disk('s3')->assertMissing($storageKey);

    // 2. Document and cascade relations must be removed from DB
    $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    $this->assertDatabaseMissing('analyses', ['id' => $analysis->id]);

    // 3. Deletion log must be written with reason retention_expired
    $this->assertDatabaseHas('deletion_logs', [
        'document_id' => $doc->id,
        'reason' => 'retention_expired',
        'file_deleted' => true,
    ]);
});

test('it does not purge active documents whose retention period has not expired', function () {
    $storageKey = 'documents/active_file.pdf';
    Storage::disk('s3')->put($storageKey, 'active content');

    $doc = Document::create([
        'guest_token_hash' => str_repeat('b', 64),
        'original_filename' => 'active.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'active content'),
        'storage_key' => $storageKey,
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addHours(12), // still active
    ]);

    $this->artisan('documents:purge-expired')
        ->assertSuccessful();

    Storage::disk('s3')->assertExists($storageKey);
    $this->assertDatabaseHas('documents', ['id' => $doc->id]);
    $this->assertDatabaseMissing('deletion_logs', ['document_id' => $doc->id]);
});

test('it aborts database deletion and retries later if storage deletion fails', function () {
    $storageKey = 'documents/failing_file.pdf';
    Storage::disk('s3')->put($storageKey, 'content');

    $doc = Document::create([
        'guest_token_hash' => str_repeat('c', 64),
        'original_filename' => 'failing.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'content'),
        'storage_key' => $storageKey,
        'status' => DocumentStatus::READY,
        'expires_at' => now()->subHour(),
    ]);

    // Mock storage delete to fail (return false)
    $mockDisk = Mockery::mock(Storage::disk('s3'))->makePartial();
    $mockDisk->shouldReceive('exists')->with($storageKey)->andReturn(true);
    $mockDisk->shouldReceive('delete')->with($storageKey)->andReturn(false);
    Storage::set('s3', $mockDisk);

    $this->artisan('documents:purge-expired')
        ->assertSuccessful();

    // Document in DB must NOT be deleted
    $this->assertDatabaseHas('documents', ['id' => $doc->id]);
    $this->assertDatabaseMissing('deletion_logs', ['document_id' => $doc->id]);
});

test('the retention job is registered to run hourly in the console schedule', function () {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())->filter(function ($event) {
        return str_contains($event->command, 'documents:purge-expired')
            || str_contains($event->command, PurgeExpiredDocuments::class);
    });

    expect($events)->not->toBeEmpty();
    expect($events->first()->expression)->toBe('0 * * * *'); // Hourly cron expression
});
