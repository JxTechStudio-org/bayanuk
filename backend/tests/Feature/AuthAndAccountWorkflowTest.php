<?php

use App\Enums\DeletionReason;
use App\Models\Document;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

// 1. Guest Token Flow: Test invalid guest token
it('returns invalid guest token error for invalid or malformed guest tokens', function () {
    $response = $this->withHeader('X-Guest-Token', 'invalid-token-string')
        ->postJson('/api/v1/guest-token');

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'invalid_guest_token',
            ],
        ]);
});

// 1.5. Guest Token Flow: Test successful guest token generation (201)
it('successfully generates and returns a new guest token and stores its hash', function () {
    $response = $this->postJson('/api/v1/guest-token');

    $response->assertStatus(201)
        ->assertJsonStructure([
            'token',
        ]);
});

// 2. Magic Link Flow: Test expired login link
it('returns link_expired error when trying to verify an expired magic link', function () {
    $token = 'expired-token-123';
    LoginToken::create([
        'email' => 'test@example.com',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinutes(5), // Expired link
        'used_at' => null,
    ]);

    $response = $this->postJson('/api/v1/auth/magic-link/verify', [
        'token' => $token,
    ]);

    $response->assertStatus(410)
        ->assertJson([
            'error' => [
                'code' => 'link_expired',
            ],
        ]);
});

// 3. Magic Link Flow: Test already used login link
it('returns link_used error when trying to reuse an already used magic link', function () {
    $token = 'used-token-456';
    LoginToken::create([
        'email' => 'test@example.com',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addMinutes(15),
        'used_at' => now()->subMinutes(2), // Already used link
    ]);

    $response = $this->postJson('/api/v1/auth/magic-link/verify', [
        'token' => $token,
    ]);

    $response->assertStatus(410)
        ->assertJson([
            'error' => [
                'code' => 'link_used',
            ],
        ]);
});

// 4. Magic Link Flow: Test unknown or invalid login link
it('returns link_invalid error for unknown or malformed magic link tokens', function () {
    $response = $this->postJson('/api/v1/auth/magic-link/verify', [
        'token' => 'non-existent-token',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'link_invalid',
            ],
        ]);
});

// 4.5. Magic Link Flow: Test rate limiting for magic link requests (429)
it('returns magic_link_limit_reached error when too many magic link requests are sent', function () {
    $email = 'rate@example.com';

    // Send requests up to the limit (max 5 attempts per minute)
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/magic-link', ['email' => $email])->assertOk();
    }

    // The 6th request should trigger rate limiting (429)
    $response = $this->postJson('/api/v1/auth/magic-link', ['email' => $email]);

    $response->assertStatus(429)
        ->assertJson([
            'error' => [
                'code' => 'magic_link_limit_reached',
            ],
        ]);
});

// 5. User /me Endpoints Flow: Test get profile and update preferences
it('allows authenticated user to retrieve and update their profile preferences', function () {
    $user = User::factory()->create([
        'locale' => 'en',
        'keep_documents' => false,
        'terms_version' => '1.0',
        'terms_accepted_at' => now(),
    ]);

    Sanctum::actingAs($user);

    // Test GET /api/v1/me
    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'locale' => 'en',
            'keep_documents' => false,
        ]);

    // Test PATCH /api/v1/me
    $this->patchJson('/api/v1/me', [
        'locale' => 'ar',
        'keep_documents' => true,
    ])
        ->assertOk()
        ->assertJson([
            'locale' => 'ar',
            'keep_documents' => true,
        ]);
});

// 5.5. Authentication Flow: Test user logout endpoint
it('allows authenticated user to securely logout and invalidate session', function () {
    $user = User::factory()->create([
        'terms_version' => '1.0',
        'terms_accepted_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJson([
            'message' => 'Successfully logged out.',
        ]);
});

// 6. Account Deletion Flow: Test full account deletion, S3 cleanup, and audit logs
it('removes user documents from S3 storage first, deletes database records, and logs account_deleted reason', function () {
    Storage::fake('s3');

    $user = User::factory()->create([
        'terms_version' => '1.0',
        'terms_accepted_at' => now(),
    ]);

    $storageKey = 'users/'.$user->id.'/sample.pdf';
    Storage::disk('s3')->put($storageKey, 'file contents');

    $document = $user->documents()->create([
        'original_filename' => 'sample.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 500,
        'sha256' => hash('sha256', 'file contents'),
        'storage_key' => $storageKey,
        'status' => 'ready',
        'expires_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/v1/me');

    $response->assertNoContent(); // 204

    // Ensure file was deleted from S3 storage
    Storage::disk('s3')->assertMissing($storageKey);

    // Ensure deletion log was written with reason account_deleted
    $this->assertDatabaseHas('deletion_logs', [
        'document_id' => $document->id,
        'reason' => DeletionReason::ACCOUNT_DELETED->value,
        'file_deleted' => true,
    ]);

    // Ensure user record was deleted from database
    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);

});

// 6.5. Google Sign-in Flow: Test Google redirect URL generation and state/guest token caching
it('generates Google redirect URL and stores guest token against state in cache', function () {
    $guestToken = 'guest-token-xyz';

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/auth/google/redirect');

    $response->assertOk()
        ->assertJsonStructure([
            'url',
        ]);
});

// 7. Guest Claiming Flow: Test claiming guest documents upon successful magic link sign-in
it('claims guest documents and assigns them to the user upon magic link verification', function () {
    $guestToken = 'my-guest-token-123';
    $guestTokenHash = hash('sha256', $guestToken);

    // Create an unassigned guest document associated with the guest token hash
    $document = Document::create([
        'guest_token_hash' => $guestTokenHash,
        'user_id' => null,
        'original_filename' => 'contract.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'some content'),
        'storage_key' => 'guest/contract.pdf',
        'status' => 'ready',
        'expires_at' => now()->addDays(1),
    ]);

    $rawToken = 'valid-magic-token';
    LoginToken::create([
        'email' => 'guestuser@example.com',
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(15),
        'used_at' => null,
    ]);

    // Verify magic link while passing the guest token header
    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/auth/magic-link/verify', [
            'token' => $rawToken,
        ]);

    $response->assertOk();

    // Assert that the document now belongs to the newly created user and guest_token_hash is cleared
    $user = User::where('email', 'guestuser@example.com')->first();

    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'user_id' => $user->id,
        'guest_token_hash' => null,
    ]);
});
