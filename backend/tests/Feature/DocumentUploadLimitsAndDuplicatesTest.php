<?php

use App\Enums\DocumentStatus;
use App\Jobs\Pipeline\ScanFile;
use App\Models\Document;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
    Cache::flush();
});

function createValidPdfContent(string $text = 'Sample document content'): string
{
    return "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\n% {$text}\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n190\n%%EOF";
}

// -------------------------------------------------------------------------------------------------
// Item 10: Daily AI Budget
// -------------------------------------------------------------------------------------------------

test('it refuses upload with 429 ai_budget_reached when daily AI budget is reached', function () {
    $guestToken = str_repeat('a', 64);
    $startOfDayRiyadh = Carbon::now('Asia/Riyadh')->startOfDay()->setTimezone('UTC');

    // Simulate AI spending of $2.00 today
    UsageEvent::create([
        'event' => 'analysis',
        'cost_usd' => 2.00,
        'created_at' => $startOfDayRiyadh->copy()->addMinutes(10),
    ]);

    $pdfContent = createValidPdfContent();
    $file = UploadedFile::fake()->createWithContent('contract.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'ai_budget_reached');

    // Verify no document was created and no file stored
    expect(Document::count())->toBe(0);
    Storage::disk('s3')->assertDirectoryEmpty('documents');
});

test('it allows upload when yesterday reached AI budget but today is fresh in Riyadh time', function () {
    $guestToken = str_repeat('a', 64);
    $yesterdayRiyadh = Carbon::now('Asia/Riyadh')->subDay()->setTimezone('UTC');

    UsageEvent::create([
        'event' => 'analysis',
        'cost_usd' => 5.00,
        'created_at' => $yesterdayRiyadh,
    ]);

    $pdfContent = createValidPdfContent();
    $file = UploadedFile::fake()->createWithContent('fresh.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(202)
        ->assertJsonStructure(['id', 'status']);

    expect(Document::count())->toBe(1);
});

// -------------------------------------------------------------------------------------------------
// Item 9: Duplicate Upload Handling
// -------------------------------------------------------------------------------------------------

test('it returns existing document when same file is re-uploaded in ready or processing status', function () {
    $guestToken = str_repeat('b', 64);
    $guestTokenHash = hash('sha256', $guestToken);

    $pdfContent = createValidPdfContent('Unique identical text for duplicate test');
    $sha256 = hash('sha256', $pdfContent);

    $existing = Document::create([
        'guest_token_hash' => $guestTokenHash,
        'original_filename' => 'first.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => $sha256,
        'storage_key' => 'documents/existing.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addHours(24),
    ]);

    $file = UploadedFile::fake()->createWithContent('second.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(202)
        ->assertJson([
            'id' => $existing->id,
            'status' => 'ready',
        ]);

    // Ensure no new document and no duplicate storage
    expect(Document::count())->toBe(1);
    expect(UsageEvent::where('event', 'upload')->count())->toBe(0);
});

test('it returns existing document with status rejected when duplicate was rejected', function () {
    $guestToken = str_repeat('c', 64);
    $guestTokenHash = hash('sha256', $guestToken);

    $pdfContent = createValidPdfContent('Rejected duplicate file');
    $sha256 = hash('sha256', $pdfContent);

    $existing = Document::create([
        'guest_token_hash' => $guestTokenHash,
        'original_filename' => 'rejected.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => $sha256,
        'storage_key' => 'documents/rejected.pdf',
        'status' => DocumentStatus::REJECTED,
        'status_detail' => 'too_many_pages',
        'expires_at' => now()->addHours(24),
    ]);

    $file = UploadedFile::fake()->createWithContent('rejected_again.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(202)
        ->assertJson([
            'id' => $existing->id,
            'status' => 'rejected',
        ]);

    expect(Document::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('it re-queues failed duplicate when under retry limit (< 2)', function () {
    $guestToken = str_repeat('d', 64);
    $guestTokenHash = hash('sha256', $guestToken);

    $pdfContent = createValidPdfContent('Failed duplicate file');
    $sha256 = hash('sha256', $pdfContent);

    $existing = Document::create([
        'guest_token_hash' => $guestTokenHash,
        'original_filename' => 'failed.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => $sha256,
        'storage_key' => 'documents/failed.pdf',
        'status' => DocumentStatus::FAILED,
        'status_detail' => 'analysis_failed',
        'expires_at' => now()->addHours(24),
    ]);

    $file = UploadedFile::fake()->createWithContent('retry_1.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(202)
        ->assertJson([
            'id' => $existing->id,
            'status' => 'uploaded',
        ]);

    $existing->refresh();
    expect($existing->status)->toBe(DocumentStatus::UPLOADED);
    expect($existing->status_detail)->toBeNull();
    Queue::assertPushed(ScanFile::class, 1);
});

test('it returns failed status without re-queueing when duplicate retry limit (2) is reached', function () {
    $guestToken = str_repeat('e', 64);
    $guestTokenHash = hash('sha256', $guestToken);

    $pdfContent = createValidPdfContent('Failed max retries file');
    $sha256 = hash('sha256', $pdfContent);

    $existing = Document::create([
        'guest_token_hash' => $guestTokenHash,
        'original_filename' => 'failed.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => $sha256,
        'storage_key' => 'documents/failed.pdf',
        'status' => DocumentStatus::FAILED,
        'status_detail' => 'analysis_failed',
        'expires_at' => now()->addHours(24),
    ]);

    // Simulate 2 previous retries
    Cache::put("document_retry:{$existing->id}", 2);

    $file = UploadedFile::fake()->createWithContent('retry_3.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(202)
        ->assertJson([
            'id' => $existing->id,
            'status' => 'failed',
        ]);

    Queue::assertNothingPushed();
});

test('same file uploaded by different user/guest is not considered a duplicate', function () {
    $guestA = str_repeat('1', 64);
    $guestB = str_repeat('2', 64);

    $pdfContent = createValidPdfContent('Cross user file');

    $fileA = UploadedFile::fake()->createWithContent('file.pdf', $pdfContent);
    $this->withHeader('X-Guest-Token', $guestA)
        ->postJson('/api/v1/documents', ['file' => $fileA])
        ->assertStatus(202);

    $fileB = UploadedFile::fake()->createWithContent('file.pdf', $pdfContent);
    $responseB = $this->withHeader('X-Guest-Token', $guestB)
        ->postJson('/api/v1/documents', ['file' => $fileB]);

    $responseB->assertStatus(202);

    // Two distinct documents created
    expect(Document::count())->toBe(2);
    expect(UsageEvent::where('event', 'upload')->count())->toBe(2);
});

// -------------------------------------------------------------------------------------------------
// Item 8: Daily Upload Limits
// -------------------------------------------------------------------------------------------------

test('it limits guests to 2 distinct uploads per day and returns 429 upload_limit_reached on the 3rd', function () {
    $guestToken = str_repeat('f', 64);

    // Upload 1
    $file1 = UploadedFile::fake()->createWithContent('doc1.pdf', createValidPdfContent('First distinct doc'));
    $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file1])
        ->assertStatus(202);

    // Upload 2
    $file2 = UploadedFile::fake()->createWithContent('doc2.pdf', createValidPdfContent('Second distinct doc'));
    $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file2])
        ->assertStatus(202);

    // Upload 3 (exceeds limit of 2)
    $file3 = UploadedFile::fake()->createWithContent('doc3.pdf', createValidPdfContent('Third distinct doc'));
    $response3 = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file3]);

    $response3->assertStatus(429)
        ->assertJsonPath('error.code', 'upload_limit_reached');

    expect(Document::count())->toBe(2);
});

test('duplicate upload does not consume daily upload count', function () {
    $guestToken = str_repeat('7', 64);

    $pdfContent = createValidPdfContent('Counting doc');
    $file1 = UploadedFile::fake()->createWithContent('doc.pdf', $pdfContent);

    // First upload: counts as 1
    $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file1])
        ->assertStatus(202);

    expect(UsageEvent::where('event', 'upload')->count())->toBe(1);

    // Re-upload identical file: should return 202 without incrementing upload count
    $fileDuplicate = UploadedFile::fake()->createWithContent('doc.pdf', $pdfContent);
    $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $fileDuplicate])
        ->assertStatus(202);

    expect(UsageEvent::where('event', 'upload')->count())->toBe(1);

    // Now a distinct second upload is still allowed (reaching 2)
    $file2 = UploadedFile::fake()->createWithContent('doc2.pdf', createValidPdfContent('Second distinct doc'));
    $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file2])
        ->assertStatus(202);

    expect(UsageEvent::where('event', 'upload')->count())->toBe(2);
});

test('it limits signed-in users to 10 uploads per day and returns 429 on the 11th', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*'], 'web');

    // Simulate 10 uploads today
    $startOfDayRiyadh = Carbon::now('Asia/Riyadh')->startOfDay()->setTimezone('UTC');
    for ($i = 0; $i < 10; $i++) {
        UsageEvent::create([
            'user_id' => $user->id,
            'event' => 'upload',
            'created_at' => $startOfDayRiyadh->copy()->addMinutes($i * 5),
        ]);
    }

    $file = UploadedFile::fake()->createWithContent('overflow.pdf', createValidPdfContent('Eleventh doc'));

    $response = $this->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'upload_limit_reached');
});
