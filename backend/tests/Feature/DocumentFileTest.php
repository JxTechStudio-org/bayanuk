<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function validGuestTokenForFile(): string
{
    return str_repeat('b', 64);
}

// ---------------------------------------------------------------------------------------------
// GET /api/v1/documents/{id}/file - Signed URL & Ownership
// ---------------------------------------------------------------------------------------------

it('requires a guest token for unauthenticated users accessing file', function () {
    $uuid = (string) Str::uuid();

    $response = $this->getJson("/api/v1/documents/{$uuid}/file");

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'invalid_guest_token');
});

it('returns 404 document_not_found when document does not exist', function () {
    $uuid = (string) Str::uuid();

    $response = $this->withHeader('X-Guest-Token', validGuestTokenForFile())
        ->getJson("/api/v1/documents/{$uuid}/file");

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

it('returns 404 document_not_found when another user tries to access the file', function () {
    $owner = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    $intruder = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $document = Document::create([
        'user_id' => $owner->id,
        'original_filename' => 'private.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'private'),
        'storage_key' => 'documents/private.pdf',
        'status' => 'ready',
        'expires_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($intruder, ['*'], 'web');

    $response = $this->getJson("/api/v1/documents/{$document->id}/file");

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

it('returns 404 document_not_found when another guest tries to access the file', function () {
    $ownerToken = str_repeat('1', 64);
    $intruderToken = str_repeat('2', 64);

    $document = Document::create([
        'guest_token_hash' => hash('sha256', $ownerToken),
        'original_filename' => 'guest.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'guest'),
        'storage_key' => 'documents/guest.pdf',
        'status' => 'ready',
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->withHeader('X-Guest-Token', $intruderToken)
        ->getJson("/api/v1/documents/{$document->id}/file");

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

it('returns 410 document_expired when document retention period has expired', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $document = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'old.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'old'),
        'storage_key' => 'documents/old.pdf',
        'status' => 'ready',
        'expires_at' => now()->subMinute(),
    ]);

    Sanctum::actingAs($user, ['*'], 'web');

    $response = $this->getJson("/api/v1/documents/{$document->id}/file");

    $response->assertStatus(410)
        ->assertJsonPath('error.code', 'document_expired');
});

it('returns 200 with signed url valid for 5 minutes for document guest owner', function () {
    Carbon::setTestNow(now());

    $guestToken = validGuestTokenForFile();
    $document = Document::create([
        'guest_token_hash' => hash('sha256', $guestToken),
        'original_filename' => 'contract.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'contract'),
        'storage_key' => 'documents/contract.pdf',
        'status' => 'ready',
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/documents/{$document->id}/file");

    $response->assertOk()
        ->assertJsonStructure([
            'url',
            'expires_at',
        ]);

    $data = $response->json();
    expect($data['url'])->not->toBeEmpty()
        ->and($data['expires_at'])->toBe(now()->addMinutes(5)->toIso8601String());
});

it('returns 200 with signed url valid for 5 minutes for authenticated user owner', function () {
    Carbon::setTestNow(now());

    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    Sanctum::actingAs($user, ['*'], 'web');

    $document = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'contract.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'contract'),
        'storage_key' => 'documents/contract.pdf',
        'status' => 'ready',
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->getJson("/api/v1/documents/{$document->id}/file");

    $response->assertOk()
        ->assertJsonStructure([
            'url',
            'expires_at',
        ]);

    $data = $response->json();
    expect($data['url'])->not->toBeEmpty()
        ->and($data['expires_at'])->toBe(now()->addMinutes(5)->toIso8601String());
});
