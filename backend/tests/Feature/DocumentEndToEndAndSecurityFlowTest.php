<?php

use App\Enums\DeletionReason;
use App\Enums\DocumentStatus;
use App\Jobs\Pipeline\ScanFile;
use App\Models\Document;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\Security\VirusScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// Helper for minimal valid PDF
function makePdf(string $unique = 'content'): string
{
    return "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\n% {$unique}\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n190\n%%EOF";
}

// -------------------------------------------------------------------------------------------------
// Scenario 1: Complete End-to-End Lifecycle for Guest
// -------------------------------------------------------------------------------------------------

test('scenario 1: complete end-to-end lifecycle for guest (upload -> scan -> poll -> view -> delete -> 404)', function () {
    $guestToken = str_repeat('1', 64);
    $pdf = UploadedFile::fake()->createWithContent('contract.pdf', makePdf('guest-lifecycle'));

    // 1. Upload
    $uploadRes = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $pdf]);

    $uploadRes->assertStatus(202)
        ->assertJson(['status' => 'uploaded']);

    $docId = $uploadRes->json('id');
    $document = Document::findOrFail($docId);
    expect($document->guest_token_hash)->toBe(hash('sha256', $guestToken));
    Storage::disk('s3')->assertExists($document->storage_key);

    // 2. ScanFile job runs in pipeline
    $safeScanner = Mockery::mock(VirusScanner::class);
    $safeScanner->shouldReceive('isSafe')->once()->andReturn(true);
    app()->instance(VirusScanner::class, $safeScanner);

    $job = new ScanFile($document);
    $job->handle(virusScanner: $safeScanner);

    $document->refresh();
    expect($document->status)->not->toBe(DocumentStatus::REJECTED)
        ->and($document->page_count)->toBeGreaterThan(0);

    // 3. Poll Status
    $statusRes = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/documents/{$docId}/status");

    $statusRes->assertOk()
        ->assertJson([
            'id' => $docId,
            'status' => $document->status->value,
            'status_detail' => null,
        ]);

    // 4. Request signed URL
    $fileRes = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/documents/{$docId}/file");

    $fileRes->assertOk()
        ->assertJsonStructure(['url', 'expires_at']);

    // 5. Request full document result
    $showRes = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/documents/{$docId}");

    $showRes->assertOk()
        ->assertJson(['id' => $docId]);

    // 6. Delete document
    $deleteRes = $this->withHeader('X-Guest-Token', $guestToken)
        ->deleteJson("/api/v1/documents/{$docId}");

    $deleteRes->assertStatus(204);

    // Verify storage file removed, DB row deleted, deletion log written
    Storage::disk('s3')->assertMissing($document->storage_key);
    $this->assertDatabaseMissing('documents', ['id' => $docId]);
    $this->assertDatabaseHas('deletion_logs', [
        'document_id' => $docId,
        'reason' => DeletionReason::USER_REQUEST->value,
        'file_deleted' => true,
    ]);

    // 7. Subsequent access must return 404 document_not_found
    $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/documents/{$docId}/status")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

// -------------------------------------------------------------------------------------------------
// Scenario 2: Complete End-to-End Lifecycle for Authenticated User
// -------------------------------------------------------------------------------------------------

test('scenario 2: complete end-to-end lifecycle for user (upload -> list -> view -> delete -> list empty)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*'], 'web');

    $pdf = UploadedFile::fake()->createWithContent('offer.pdf', makePdf('user-lifecycle'));

    // 1. Upload
    $uploadRes = $this->postJson('/api/v1/documents', ['file' => $pdf]);
    $uploadRes->assertStatus(202);
    $docId = $uploadRes->json('id');

    // 2. Paginated list includes the uploaded document
    $listRes1 = $this->getJson('/api/v1/documents');
    $listRes1->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $docId);

    // 3. View signed file URL
    $this->getJson("/api/v1/documents/{$docId}/file")
        ->assertOk()
        ->assertJsonStructure(['url', 'expires_at']);

    // 4. Delete document
    $this->deleteJson("/api/v1/documents/{$docId}")
        ->assertStatus(204);

    // 5. Paginated list is now empty
    $listRes2 = $this->getJson('/api/v1/documents');
    $listRes2->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

// -------------------------------------------------------------------------------------------------
// Scenario 3: Wrong Owner Cross-Access & Deletion Security
// -------------------------------------------------------------------------------------------------

test('scenario 3: intruder cannot delete or view another owner document across guest and user boundaries', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $guestOwnerToken = str_repeat('a', 64);
    $guestIntruderToken = str_repeat('b', 64);

    $userDoc = Document::create([
        'user_id' => $owner->id,
        'original_filename' => 'user.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'user-secure'),
        'storage_key' => 'documents/user-secure.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addDays(30),
    ]);
    Storage::disk('s3')->put($userDoc->storage_key, 'user-content');

    $guestDoc = Document::create([
        'guest_token_hash' => hash('sha256', $guestOwnerToken),
        'original_filename' => 'guest.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'guest-secure'),
        'storage_key' => 'documents/guest-secure.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addHours(24),
    ]);
    Storage::disk('s3')->put($guestDoc->storage_key, 'guest-content');

    // Case A: Intruder user trying to DELETE owner user document -> 404
    Sanctum::actingAs($intruder, ['*'], 'web');
    $this->deleteJson("/api/v1/documents/{$userDoc->id}")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    // Case B: Intruder guest trying to DELETE owner guest document -> 404
    $this->withHeader('X-Guest-Token', $guestIntruderToken)
        ->deleteJson("/api/v1/documents/{$guestDoc->id}")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    // Case C: Authenticated user trying to access guest document -> 404
    Sanctum::actingAs($intruder, ['*'], 'web');
    $this->getJson("/api/v1/documents/{$guestDoc->id}")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    // Case D: Guest trying to access authenticated user document -> 404
    $this->withHeader('X-Guest-Token', $guestOwnerToken)
        ->getJson("/api/v1/documents/{$userDoc->id}")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    // Both files and documents remain 100% intact
    Storage::disk('s3')->assertExists($userDoc->storage_key);
    Storage::disk('s3')->assertExists($guestDoc->storage_key);
    $this->assertDatabaseHas('documents', ['id' => $userDoc->id]);
    $this->assertDatabaseHas('documents', ['id' => $guestDoc->id]);
});

// -------------------------------------------------------------------------------------------------
// Scenario 4: Guest Rate Limit by IP (Device hopping / token change)
// -------------------------------------------------------------------------------------------------

test('scenario 4: guest limit is enforced by IP when a user clears guest token on same device/IP', function () {
    $token1 = str_repeat('c', 64);
    $token2 = str_repeat('d', 64);
    $clientIp = '198.51.100.42';

    // Upload 1 with Token 1
    $file1 = UploadedFile::fake()->createWithContent('doc1.pdf', makePdf('doc-1'));
    $this->withServerVariables(['REMOTE_ADDR' => $clientIp])
        ->withHeader('X-Guest-Token', $token1)
        ->postJson('/api/v1/documents', ['file' => $file1])
        ->assertStatus(202);

    // Upload 2 with Token 1
    $file2 = UploadedFile::fake()->createWithContent('doc2.pdf', makePdf('doc-2'));
    $this->withServerVariables(['REMOTE_ADDR' => $clientIp])
        ->withHeader('X-Guest-Token', $token1)
        ->postJson('/api/v1/documents', ['file' => $file2])
        ->assertStatus(202);

    // User clears token and receives Token 2, but comes from the SAME IP address
    $file3 = UploadedFile::fake()->createWithContent('doc3.pdf', makePdf('doc-3'));
    $response3 = $this->withServerVariables(['REMOTE_ADDR' => $clientIp])
        ->withHeader('X-Guest-Token', $token2)
        ->postJson('/api/v1/documents', ['file' => $file3]);

    $response3->assertStatus(429)
        ->assertJsonPath('error.code', 'upload_limit_reached');

    expect(Document::count())->toBe(2);
});

// -------------------------------------------------------------------------------------------------
// Scenario 5: Duplicate Upload during In-Progress Processing
// -------------------------------------------------------------------------------------------------

test('scenario 5: duplicate upload while document is in analyzing status returns existing document as-is', function () {
    Queue::fake();

    $guestToken = str_repeat('e', 64);
    $pdfContent = makePdf('in-progress-content');
    $sha256 = hash('sha256', $pdfContent);

    $existing = Document::create([
        'guest_token_hash' => hash('sha256', $guestToken),
        'original_filename' => 'initial.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => $sha256,
        'storage_key' => 'documents/initial.pdf',
        'status' => DocumentStatus::ANALYZING,
        'status_detail' => null,
        'expires_at' => now()->addHours(24),
    ]);

    $file = UploadedFile::fake()->createWithContent('duplicate.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $file]);

    $response->assertStatus(202)
        ->assertJson([
            'id' => $existing->id,
            'status' => 'analyzing',
        ]);

    // Ensure no second document, no queue dispatch, no storage duplication
    expect(Document::count())->toBe(1);
    Queue::assertNothingPushed();
    expect(UsageEvent::where('event', 'upload')->count())->toBe(0);
});

// -------------------------------------------------------------------------------------------------
// Scenario 6: PDF-Only Restriction: DOCX Rejected with 422 file_type_not_supported
// -------------------------------------------------------------------------------------------------

test('scenario 6: refuses DOCX file with 422 file_type_not_supported when allowed_mimes is pdf only', function () {
    Queue::fake();

    $guestToken = str_repeat('f', 64);
    $docxFile = UploadedFile::fake()->create('job_offer.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', ['file' => $docxFile]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'file_type_not_supported')
        ->assertJsonPath('error.message', 'The file type is not supported. Please upload a PDF file.');

    expect(Document::count())->toBe(0);
    Queue::assertNothingPushed();
});
