<?php

use App\Jobs\Pipeline\ScanFile;
use App\Models\Document;
use App\Services\Security\VirusScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// Helper for generating a valid 64-char guest token
function validGuestToken(): string
{
    return str_repeat('a', 64);
}

// Helper to create a minimal real PDF binary content
function createMinimalPdfContent(): string
{
    return "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n190\n%%EOF";
}

// Helper to create a real PDF binary with specific page count
function createPdfWithPages(int $pageCount): string
{
    $out = "%PDF-1.4\n";
    $kids = [];
    $objects = [];

    for ($i = 1; $i <= $pageCount; $i++) {
        $pageObjNum = 2 + $i;
        $kids[] = "{$pageObjNum} 0 R";
        $objects[] = "{$pageObjNum} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
    }

    $kidsStr = implode(' ', $kids);
    $out .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $out .= "2 0 obj\n<< /Type /Pages /Kids [{$kidsStr}] /Count {$pageCount} >>\nendobj\n";

    foreach ($objects as $obj) {
        $out .= $obj;
    }

    $out .= "xref\n0 ".(3 + $pageCount)."\n";
    $out .= "trailer\n<< /Size ".(3 + $pageCount)." /Root 1 0 R >>\nstartxref\n500\n%%EOF";

    return $out;
}

// ---------------------------------------------------------------------------------------------
// POST /api/v1/documents - Validation & Auth
// ---------------------------------------------------------------------------------------------

it('refuses upload without a guest token for unauthenticated users', function () {
    $file = UploadedFile::fake()->createWithContent('test.pdf', createMinimalPdfContent());

    $response = $this->postJson('/api/v1/documents', [
        'file' => $file,
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'invalid_guest_token');
});

it('refuses upload when file field is missing', function () {
    $response = $this->withHeader('X-Guest-Token', validGuestToken())
        ->postJson('/api/v1/documents', []);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['file']]]);
});

it('returns 422 file_too_large when file exceeds 10 MB', function () {
    // 10 MB is 10485760 bytes. 10485761 bytes exceeds limit.
    $oversizedFile = UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf');

    $response = $this->withHeader('X-Guest-Token', validGuestToken())
        ->postJson('/api/v1/documents', [
            'file' => $oversizedFile,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'file_too_large');
});

it('returns 422 file_type_not_supported when file content is not PDF or DOCX', function () {
    $txtFile = UploadedFile::fake()->createWithContent('notes.txt', 'This is plain text');

    $response = $this->withHeader('X-Guest-Token', validGuestToken())
        ->postJson('/api/v1/documents', [
            'file' => $txtFile,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'file_type_not_supported');
});

it('validates file by content, rejecting fake PDF files', function () {
    // File named fake.pdf but containing plain text HTML
    $fakePdf = UploadedFile::fake()->createWithContent('fake.pdf', '<!DOCTYPE html><html><body>fake</body></html>');

    $response = $this->withHeader('X-Guest-Token', validGuestToken())
        ->postJson('/api/v1/documents', [
            'file' => $fakePdf,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'file_type_not_supported');
});

it('accepts a valid PDF file for a guest and returns 202 with id and status uploaded', function () {
    Queue::fake();

    $guestToken = validGuestToken();
    $pdf = UploadedFile::fake()->createWithContent('contract.pdf', createMinimalPdfContent());

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/documents', [
            'file' => $pdf,
        ]);

    $response->assertStatus(202)
        ->assertJsonStructure([
            'id',
            'status',
        ])
        ->assertJson([
            'status' => 'uploaded',
        ]);

    $docId = $response->json('id');
    $doc = Document::find($docId);

    expect($doc)->not->toBeNull()
        ->and($doc->user_id)->toBeNull()
        ->and($doc->guest_token_hash)->toBe(hash('sha256', $guestToken))
        ->and($doc->original_filename)->toBe('contract.pdf')
        ->and($doc->mime_type)->toBe('application/pdf')
        ->and($doc->status->value)->toBe('uploaded')
        ->and($doc->expires_at)->not->toBeNull();

    // Check file was stored in private S3 disk
    Storage::disk('s3')->assertExists($doc->storage_key);
});

// ---------------------------------------------------------------------------------------------
// Item 2: Page count check in pipeline (> 30 pages marked rejected with too_many_pages)
// ---------------------------------------------------------------------------------------------

it('creates document and dispatches ScanFile when file has more than 30 pages without upload error', function () {
    Queue::fake();

    $pdfContent = createPdfWithPages(35);
    $oversizedPagesPdf = UploadedFile::fake()->createWithContent('35pages.pdf', $pdfContent);

    $response = $this->withHeader('X-Guest-Token', validGuestToken())
        ->postJson('/api/v1/documents', [
            'file' => $oversizedPagesPdf,
        ]);

    $response->assertStatus(202)
        ->assertJson([
            'status' => 'uploaded',
        ]);

    $docId = $response->json('id');
    $doc = Document::find($docId);

    expect($doc)->not->toBeNull()
        ->and($doc->status->value)->toBe('uploaded');

    Queue::assertPushed(ScanFile::class, function ($job) use ($doc) {
        return $job->document->id === $doc->id;
    });
});

it('marks document rejected with too_many_pages and removes file from storage when ScanFile detects > 30 pages', function () {
    $pdfContent = createPdfWithPages(35);
    $storageKey = 'documents/test-35-pages.pdf';
    Storage::disk('s3')->put($storageKey, $pdfContent);

    $document = Document::create([
        'original_filename' => '35pages.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => hash('sha256', $pdfContent),
        'storage_key' => $storageKey,
        'status' => 'uploaded',
        'expires_at' => now()->addHours(24),
    ]);

    // Run ScanFile job
    $job = new ScanFile($document);
    $job->handle();

    $document->refresh();

    expect($document->status->value)->toBe('rejected')
        ->and($document->status_detail)->toBe('too_many_pages')
        ->and($document->page_count)->toBeNull();

    // Confirm file was deleted from RustFS/S3 storage
    Storage::disk('s3')->assertMissing($storageKey);
});

it('sets page_count on document when pages are within limit (<= 30)', function () {
    $pdfContent = createPdfWithPages(5);
    $storageKey = 'documents/test-5-pages.pdf';
    Storage::disk('s3')->put($storageKey, $pdfContent);

    $document = Document::create([
        'original_filename' => '5pages.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => hash('sha256', $pdfContent),
        'storage_key' => $storageKey,
        'status' => 'uploaded',
        'expires_at' => now()->addHours(24),
    ]);

    $job = new ScanFile($document);
    $job->handle();

    $document->refresh();

    expect($document->status->value)->not->toBe('rejected')
        ->and($document->page_count)->toBe(5);

    // File must NOT be deleted
    Storage::disk('s3')->assertExists($storageKey);
});

// ---------------------------------------------------------------------------------------------
// Item 3: ClamAV scan as the first pipeline job (unsafe file marked rejected with unsafe_file)
// ---------------------------------------------------------------------------------------------

it('marks document rejected with unsafe_file and removes file from storage when ClamAV detects malware', function () {
    // Mock VirusScanner to report infected file
    $mockScanner = Mockery::mock(VirusScanner::class);
    $mockScanner->shouldReceive('isSafe')->once()->andReturn(false);
    app()->instance(VirusScanner::class, $mockScanner);

    $pdfContent = createPdfWithPages(2);
    $storageKey = 'documents/infected.pdf';
    Storage::disk('s3')->put($storageKey, $pdfContent);

    $document = Document::create([
        'original_filename' => 'infected.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => hash('sha256', $pdfContent),
        'storage_key' => $storageKey,
        'status' => 'uploaded',
        'expires_at' => now()->addHours(24),
    ]);

    $job = new ScanFile($document);
    $job->handle(virusScanner: $mockScanner);

    $document->refresh();

    expect($document->status->value)->toBe('rejected')
        ->and($document->status_detail)->toBe('unsafe_file');

    // Confirm file was deleted from RustFS/S3 storage
    Storage::disk('s3')->assertMissing($storageKey);
});

it('allows safe file to pass ClamAV scan and continue processing', function () {
    $mockScanner = Mockery::mock(VirusScanner::class);
    $mockScanner->shouldReceive('isSafe')->once()->andReturn(true);
    app()->instance(VirusScanner::class, $mockScanner);

    $pdfContent = createPdfWithPages(2);
    $storageKey = 'documents/clean.pdf';
    Storage::disk('s3')->put($storageKey, $pdfContent);

    $document = Document::create([
        'original_filename' => 'clean.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfContent),
        'sha256' => hash('sha256', $pdfContent),
        'storage_key' => $storageKey,
        'status' => 'uploaded',
        'expires_at' => now()->addHours(24),
    ]);

    $job = new ScanFile($document);
    $job->handle(virusScanner: $mockScanner);

    $document->refresh();

    expect($document->status->value)->not->toBe('rejected')
        ->and($document->status_detail)->toBeNull();

    Storage::disk('s3')->assertExists($storageKey);
});
