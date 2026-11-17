<?php

use App\Enums\AnalysisStatus;
use App\Enums\DeletionReason;
use App\Enums\DocumentStatus;
use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use App\Enums\FindingSource;
use App\Models\Analysis;
use App\Models\Document;
use App\Models\ExtractedTerm;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function validGuestTokenForEndpoints(): string
{
    return str_repeat('c', 64);
}

// ---------------------------------------------------------------------------------------------
// GET /api/v1/documents/{id}/status (Item 5 & Item 7)
// ---------------------------------------------------------------------------------------------

it('returns status and status_detail for document owner (guest)', function () {
    $guestToken = validGuestTokenForEndpoints();
    $doc = Document::create([
        'guest_token_hash' => hash('sha256', $guestToken),
        'original_filename' => 'contract.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'status-guest'),
        'storage_key' => 'documents/status-guest.pdf',
        'status' => DocumentStatus::ANALYZING,
        'status_detail' => null,
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/documents/{$doc->id}/status");

    $response->assertOk()
        ->assertJson([
            'id' => $doc->id,
            'status' => 'analyzing',
            'status_detail' => null,
        ]);
});

it('returns 404 document_not_found on status endpoint for another guest', function () {
    $ownerToken = str_repeat('1', 64);
    $intruderToken = str_repeat('2', 64);

    $doc = Document::create([
        'guest_token_hash' => hash('sha256', $ownerToken),
        'original_filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'doc-1'),
        'storage_key' => 'documents/doc-1.pdf',
        'status' => DocumentStatus::SCANNING,
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->withHeader('X-Guest-Token', $intruderToken)
        ->getJson("/api/v1/documents/{$doc->id}/status");

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

it('returns 410 document_expired on status endpoint when expired', function () {
    $guestToken = validGuestTokenForEndpoints();
    $doc = Document::create([
        'guest_token_hash' => hash('sha256', $guestToken),
        'original_filename' => 'expired.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'expired'),
        'storage_key' => 'documents/expired.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/documents/{$doc->id}/status");

    $response->assertStatus(410)
        ->assertJsonPath('error.code', 'document_expired');
});

// ---------------------------------------------------------------------------------------------
// GET /api/v1/documents/{id} - Result endpoint (Item 6 & Item 7)
// ---------------------------------------------------------------------------------------------

it('returns full document result with analysis, terms and findings for owner', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    Sanctum::actingAs($user, ['*'], 'web');

    $doc = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'offer.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'sha256' => hash('sha256', 'offer-user'),
        'storage_key' => 'documents/offer.pdf',
        'status' => DocumentStatus::READY,
        'document_type' => 'employment_contract',
        'language' => 'ar',
        'page_count' => 12,
        'expires_at' => now()->addDays(30),
    ]);

    $analysis = Analysis::create([
        'document_id' => $doc->id,
        'status' => AnalysisStatus::SUCCEEDED,
        'provider' => 'gemini',
        'model' => 'gemini-3.8-flash',
        'prompt_version' => 'v1',
        'schema_version' => '1.0',
        'summary' => 'ملخص العقد الوظيفي',
        'summary_language' => 'ar',
    ]);

    ExtractedTerm::create([
        'analysis_id' => $analysis->id,
        'key' => 'compensation.basic_salary',
        'value_number' => 8000,
        'unit' => 'SAR',
        'found' => true,
        'page_number' => 3,
        'source_quote' => 'الراتب الأساسي 8000 ريال',
    ]);

    ExtractedTerm::create([
        'analysis_id' => $analysis->id,
        'key' => 'notice.employer_days',
        'found' => false,
    ]);

    Finding::create([
        'analysis_id' => $analysis->id,
        'category' => FindingCategory::NON_COMPETE,
        'severity' => FindingSeverity::ATTENTION,
        'source' => FindingSource::RULE,
        'title' => 'شرط عدم المنافسة',
        'explanation' => 'يحتوي العقد على بند عدم منافسة لمدة سنة',
        'page_number' => 9,
        'source_quote' => 'يلتزم الطرف الثاني بعدم المنافسة',
        'sort_order' => 1,
    ]);

    $response = $this->getJson("/api/v1/documents/{$doc->id}");

    $response->assertOk()
        ->assertJson([
            'id' => $doc->id,
            'status' => 'ready',
            'document_type' => 'employment_contract',
            'language' => 'ar',
            'page_count' => 12,
            'analysis' => [
                'id' => $analysis->id,
                'summary' => 'ملخص العقد الوظيفي',
                'summary_language' => 'ar',
                'terms' => [
                    [
                        'key' => 'compensation.basic_salary',
                        'value' => 8000,
                        'unit' => 'SAR',
                        'found' => true,
                        'page' => 3,
                        'quote' => 'الراتب الأساسي 8000 ريال',
                    ],
                    [
                        'key' => 'notice.employer_days',
                        'found' => false,
                    ],
                ],
                'findings' => [
                    [
                        'category' => 'non_compete',
                        'severity' => 'attention',
                        'title' => 'شرط عدم المنافسة',
                        'explanation' => 'يحتوي العقد على بند عدم منافسة لمدة سنة',
                        'page' => 9,
                        'quote' => 'يلتزم الطرف الثاني بعدم المنافسة',
                    ],
                ],
            ],
        ]);

    // Check not found term does not have value, unit, page, quote
    $terms = $response->json('analysis.terms');
    $notFoundTerm = collect($terms)->firstWhere('key', 'notice.employer_days');
    expect($notFoundTerm)->toHaveKey('found', false)
        ->and($notFoundTerm)->not->toHaveKey('value')
        ->and($notFoundTerm)->not->toHaveKey('unit')
        ->and($notFoundTerm)->not->toHaveKey('page')
        ->and($notFoundTerm)->not->toHaveKey('quote');
});

it('returns 404 document_not_found on result endpoint for wrong user', function () {
    $owner = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    $intruder = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $doc = Document::create([
        'user_id' => $owner->id,
        'original_filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'doc-owner'),
        'storage_key' => 'documents/doc-owner.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($intruder, ['*'], 'web');

    $response = $this->getJson("/api/v1/documents/{$doc->id}");

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

// ---------------------------------------------------------------------------------------------
// GET /api/v1/documents - Paginated list (Item 6)
// ---------------------------------------------------------------------------------------------

it('returns 401 unauthorized when unauthenticated on documents list', function () {
    $response = $this->getJson('/api/v1/documents');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthorized');
});

it('returns paginated list of documents for authenticated user only', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    $otherUser = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $doc1 = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'first.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'first'),
        'storage_key' => 'documents/first.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addDays(30),
    ]);

    $doc2 = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'second.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'sha256' => hash('sha256', 'second'),
        'storage_key' => 'documents/second.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addDays(30),
    ]);

    Document::create([
        'user_id' => $otherUser->id,
        'original_filename' => 'other.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'other'),
        'storage_key' => 'documents/other.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($user, ['*'], 'web');

    $response = $this->getJson('/api/v1/documents');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'original_filename', 'status', 'status_detail', 'created_at', 'expires_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('meta.total', 2);

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($doc1->id)
        ->and($ids)->toContain($doc2->id)
        ->and($ids)->toHaveCount(2);
});

// ---------------------------------------------------------------------------------------------
// DELETE /api/v1/documents/{id} (Item 6, Item 7, Item 11)
// ---------------------------------------------------------------------------------------------

it('deletes document: removes file from RustFS first, writes deletion log, and deletes data (cascade)', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    Sanctum::actingAs($user, ['*'], 'web');

    $storageKey = 'documents/to-delete.pdf';
    Storage::disk('s3')->put($storageKey, 'content');

    $doc = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'delete_me.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'delete_me'),
        'storage_key' => $storageKey,
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addDays(30),
    ]);

    $analysis = Analysis::create([
        'document_id' => $doc->id,
        'status' => AnalysisStatus::SUCCEEDED,
        'provider' => 'gemini',
        'model' => 'gemini-3.8-flash',
        'prompt_version' => 'v1',
        'schema_version' => '1.0',
    ]);

    $term = ExtractedTerm::create([
        'analysis_id' => $analysis->id,
        'key' => 'test',
        'found' => true,
    ]);

    $response = $this->deleteJson("/api/v1/documents/{$doc->id}");

    $response->assertStatus(204);

    // 1. File removed from storage
    Storage::disk('s3')->assertMissing($storageKey);

    // 2. Database records deleted via cascade
    $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    $this->assertDatabaseMissing('analyses', ['id' => $analysis->id]);
    $this->assertDatabaseMissing('extracted_terms', ['id' => $term->id]);

    // 3. Deletion log written with reason: user_request and file_deleted: true
    $this->assertDatabaseHas('deletion_logs', [
        'document_id' => $doc->id,
        'reason' => DeletionReason::USER_REQUEST->value,
        'file_deleted' => true,
    ]);
});

it('aborts deletion and keeps document intact if storage deletion fails', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    Sanctum::actingAs($user, ['*'], 'web');

    $doc = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'fail.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'fail'),
        'storage_key' => 'documents/cannot-delete.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->addDays(30),
    ]);

    // Mock S3 disk to simulate failure deleting file
    $mockDisk = Mockery::mock(Filesystem::class);
    $mockDisk->shouldReceive('exists')->with('documents/cannot-delete.pdf')->andReturn(true);
    $mockDisk->shouldReceive('delete')->with('documents/cannot-delete.pdf')->andReturn(false);
    Storage::shouldReceive('disk')->with('s3')->andReturn($mockDisk);

    $response = $this->deleteJson("/api/v1/documents/{$doc->id}");

    $response->assertStatus(500)
        ->assertJsonPath('error.code', 'server_error');

    // Document must NOT be deleted
    $this->assertDatabaseHas('documents', ['id' => $doc->id]);
    $this->assertDatabaseMissing('deletion_logs', ['document_id' => $doc->id]);
});

// ---------------------------------------------------------------------------------------------
// Security & Edge Cases: Malformed IDs and Expired Documents by Wrong Owner (Technical Plan Section 8)
// ---------------------------------------------------------------------------------------------

it('returns 404 document_not_found for expired document belonging to another guest (never leaks existence)', function () {
    $ownerToken = str_repeat('1', 64);
    $intruderToken = str_repeat('2', 64);

    $doc = Document::create([
        'guest_token_hash' => hash('sha256', $ownerToken),
        'original_filename' => 'expired_other.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'expired_other'),
        'storage_key' => 'documents/expired_other.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->subDay(),
    ]);

    // An intruder querying an expired document belonging to someone else must receive 404, NEVER 410
    $response = $this->withHeader('X-Guest-Token', $intruderToken)
        ->getJson("/api/v1/documents/{$doc->id}/status");

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    $showResponse = $this->withHeader('X-Guest-Token', $intruderToken)
        ->getJson("/api/v1/documents/{$doc->id}");

    $showResponse->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

it('returns 404 document_not_found for expired document belonging to another signed-in user', function () {
    $owner = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    $intruder = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $doc = Document::create([
        'user_id' => $owner->id,
        'original_filename' => 'user_expired.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'user_expired'),
        'storage_key' => 'documents/user_expired.pdf',
        'status' => DocumentStatus::READY,
        'expires_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($intruder, ['*'], 'web');

    $response = $this->getJson("/api/v1/documents/{$doc->id}");

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

it('returns 404 document_not_found when requesting an invalid non-uuid id like documents/abc', function () {
    $guestToken = validGuestTokenForEndpoints();

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson('/api/v1/documents/abc');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    $statusResponse = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson('/api/v1/documents/abc/status');

    $statusResponse->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    $fileResponse = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson('/api/v1/documents/abc/file');

    $fileResponse->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');

    $deleteResponse = $this->withHeader('X-Guest-Token', $guestToken)
        ->deleteJson('/api/v1/documents/abc');

    $deleteResponse->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});

it('returns 404 document_not_found when requesting with a trailing slash like documents/abc/', function () {
    $guestToken = validGuestTokenForEndpoints();

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson('/api/v1/documents/abc/');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'document_not_found');
});
