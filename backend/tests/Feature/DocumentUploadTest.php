<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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

it('accepts a valid PDF file for an authenticated user without guest token', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    Sanctum::actingAs($user, ['*'], 'web');

    $pdf = UploadedFile::fake()->createWithContent('offer.pdf', createMinimalPdfContent());

    $response = $this->postJson('/api/v1/documents', [
        'file' => $pdf,
    ]);

    $response->assertStatus(202)
        ->assertJson([
            'status' => 'uploaded',
        ]);

    $docId = $response->json('id');
    $doc = Document::find($docId);

    expect($doc)->not->toBeNull()
        ->and($doc->user_id)->toBe($user->id)
        ->and($doc->guest_token_hash)->toBeNull()
        ->and($doc->status->value)->toBe('uploaded');

    Storage::disk('s3')->assertExists($doc->storage_key);
});
