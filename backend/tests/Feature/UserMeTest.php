<?php

use App\Enums\DeletionReason;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------------------------
// GET /api/v1/me
// ---------------------------------------------------------------------------------------------

it('returns 401 unauthorized when unauthenticated', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthorized');
});

it('returns authenticated user profile without exposing sensitive fields', function () {
    $user = User::factory()->create([
        'email' => 'danah@example.com',
        'name' => 'Danah',
        'locale' => 'ar',
        'keep_documents' => true,
        'google_id' => 'secret-google-id',
        'terms_version' => '1.0',
        'terms_accepted_at' => now(),
    ]);

    Sanctum::actingAs($user, ['*'], 'web');

    $response = $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'email' => 'danah@example.com',
            'name' => 'Danah',
            'locale' => 'ar',
            'keep_documents' => true,
        ]);

    $data = $response->json();
    expect($data)->not->toHaveKey('google_id')
        ->and($data)->not->toHaveKey('remember_token')
        ->and($data)->not->toHaveKey('terms_version');
});

// ---------------------------------------------------------------------------------------------
// PATCH /api/v1/me
// ---------------------------------------------------------------------------------------------

it('allows updating profile preferences', function () {
    $user = User::factory()->create([
        'locale' => 'en',
        'keep_documents' => false,
        'terms_version' => '1.0',
        'terms_accepted_at' => now(),
    ]);

    Sanctum::actingAs($user, ['*'], 'web');

    $this->patchJson('/api/v1/me', [
        'locale' => 'ar',
        'keep_documents' => true,
    ])
        ->assertOk()
        ->assertJson([
            'locale' => 'ar',
            'keep_documents' => true,
        ]);

    expect($user->fresh()->locale)->toBe('ar')
        ->and($user->fresh()->keep_documents)->toBeTrue();
});

it('rejects invalid locale and non-boolean keep_documents with validation_error (422)', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    Sanctum::actingAs($user, ['*'], 'web');

    $this->patchJson('/api/v1/me', ['locale' => 'fr'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['locale']]]);

    $this->patchJson('/api/v1/me', ['keep_documents' => 'not-a-bool'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['keep_documents']]]);
});

// ---------------------------------------------------------------------------------------------
// POST /api/v1/auth/logout
// ---------------------------------------------------------------------------------------------

it('logs out authenticated user securely', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    Sanctum::actingAs($user, ['*'], 'web');

    $this->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJson(['message' => 'Successfully logged out.']);
});

it('returns 401 on logout when unauthenticated', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthorized');
});

// ---------------------------------------------------------------------------------------------
// DELETE /api/v1/me (Account Deletion)
// ---------------------------------------------------------------------------------------------

it('removes storage files first, writes audit logs, and deletes database records', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $storageKey1 = 'users/'.$user->id.'/doc1.pdf';
    $storageKey2 = 'users/'.$user->id.'/doc2.pdf';
    Storage::disk('s3')->put($storageKey1, 'pdf-content-1');
    Storage::disk('s3')->put($storageKey2, 'pdf-content-2');

    $doc1 = $user->documents()->create([
        'original_filename' => 'doc1.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', '1'),
        'storage_key' => $storageKey1,
        'status' => 'ready',
        'expires_at' => now()->addDays(30),
    ]);

    $doc2 = $user->documents()->create([
        'original_filename' => 'doc2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'sha256' => hash('sha256', '2'),
        'storage_key' => $storageKey2,
        'status' => 'ready',
        'expires_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($user, ['*'], 'web');

    $response = $this->deleteJson('/api/v1/me');
    $response->assertNoContent(); // 204

    // 1. Files are removed from storage
    Storage::disk('s3')->assertMissing($storageKey1);
    Storage::disk('s3')->assertMissing($storageKey2);

    // 2. Audit logs exist with reason account_deleted and file_deleted true
    $this->assertDatabaseHas('deletion_logs', [
        'document_id' => $doc1->id,
        'reason' => DeletionReason::ACCOUNT_DELETED->value,
        'file_deleted' => true,
    ]);
    $this->assertDatabaseHas('deletion_logs', [
        'document_id' => $doc2->id,
        'reason' => DeletionReason::ACCOUNT_DELETED->value,
        'file_deleted' => true,
    ]);

    // 3. User and documents are deleted
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('documents', ['id' => $doc1->id]);
    $this->assertDatabaseMissing('documents', ['id' => $doc2->id]);
});

it('aborts deletion and keeps user account intact if storage deletion fails', function () {
    $user = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $doc = $user->documents()->create([
        'original_filename' => 'important.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'fail'),
        'storage_key' => 'cannot-delete.pdf',
        'status' => 'ready',
        'expires_at' => now()->addDays(30),
    ]);

    // Mock storage to fail on delete
    $mockDisk = Mockery::mock(Filesystem::class);
    $mockDisk->shouldReceive('delete')->with('cannot-delete.pdf')->andReturn(false);
    Storage::shouldReceive('disk')->with('s3')->andReturn($mockDisk);

    Sanctum::actingAs($user, ['*'], 'web');

    $response = $this->deleteJson('/api/v1/me');
    $response->assertStatus(500)
        ->assertJsonPath('error.code', 'server_error');

    // User and document must still exist
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('documents', ['id' => $doc->id]);

    // No fake audit log was written
    $this->assertDatabaseMissing('deletion_logs', ['document_id' => $doc->id]);
});

it('deletes only target user documents and leaves other users untouched', function () {
    Storage::fake('s3');

    $userA = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    $userB = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);

    $docA = $userA->documents()->create([
        'original_filename' => 'a.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        'sha256' => hash('sha256', 'a'), 'storage_key' => 'a.pdf', 'status' => 'ready', 'expires_at' => now()->addDay(),
    ]);
    $docB = $userB->documents()->create([
        'original_filename' => 'b.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        'sha256' => hash('sha256', 'b'), 'storage_key' => 'b.pdf', 'status' => 'ready', 'expires_at' => now()->addDay(),
    ]);

    Sanctum::actingAs($userA, ['*'], 'web');
    $this->deleteJson('/api/v1/me')->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $userA->id]);
    $this->assertDatabaseMissing('documents', ['id' => $docA->id]);

    $this->assertDatabaseHas('users', ['id' => $userB->id]);
    $this->assertDatabaseHas('documents', ['id' => $docB->id]);
});
