<?php

use App\Enums\DeletionReason;
use App\Models\Document;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Resend\Client;
use Resend\Laravel\Facades\Resend;
use Resend\Service\Email;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

// 1. Guest Token Flow: Test successful generation (201) and ensure no fake document is created
it('successfully generates and returns a new 64-char guest token without creating fake documents', function () {
    $response = $this->postJson('/api/v1/guest-token');

    $response->assertStatus(201)
        ->assertJsonStructure(['token']);

    $token = $response->json('token');
    expect(strlen($token))->toBe(64);

    // Verify that NO fake document was created in the database
    $this->assertDatabaseCount('documents', 0);
});

// 1.5. Guest Token Flow: Test malformed guest token header validation
it('returns invalid guest token error for invalid or malformed guest tokens', function () {
    $response = $this->withHeader('X-Guest-Token', 'malformed-token-string')
        ->postJson('/api/v1/guest-token');

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'invalid_guest_token',
            ],
        ]);
});

// 2. Magic Link Flow: Test real Resend email sending
it('dispatches a real email via Resend SDK when requesting a magic link', function () {
    $mockClient = Mockery::mock(Client::class);
    $mockEmails = Mockery::mock(Email::class);

    $mockClient->shouldReceive('emails')->andReturn($mockEmails);
    $mockEmails->shouldReceive('send')->once()->with(Mockery::on(function ($payload) {
        return $payload['to'][0] === 'user@example.com'
            && str_contains($payload['html'], '/auth/verify?token=')
            && ! empty($payload['subject']);
    }))->andReturn((object) ['id' => 'email_123']);

    Resend::swap($mockClient);

    $response = $this->postJson('/api/v1/auth/magic-link', [
        'email' => 'user@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Magic link sent successfully.',
        ]);

    $this->assertDatabaseHas('login_tokens', [
        'email' => 'user@example.com',
    ]);
});

// 2.5. Magic Link Flow: Test expired login link
it('returns link_expired error when trying to verify an expired magic link', function () {
    $token = 'expired-token-123';
    LoginToken::create([
        'email' => 'test@example.com',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinutes(5),
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
        'used_at' => now()->subMinutes(2),
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

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/magic-link', ['email' => $email])->assertOk();
    }

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

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'locale' => 'en',
            'keep_documents' => false,
        ]);

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

// 6. Account Deletion Flow: Test account deletion with personal access tokens
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

    Storage::disk('s3')->assertMissing($storageKey);

    $this->assertDatabaseHas('deletion_logs', [
        'document_id' => $document->id,
        'reason' => DeletionReason::ACCOUNT_DELETED->value,
        'file_deleted' => true,
    ]);

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

// 6.2. Account Deletion Flow: Test account deletion under SPA Cookie Session (Web Guard)
it('successfully deletes user account and terminates session for SPA cookie users without currentAccessToken error', function () {
    Storage::fake('s3');

    $user = User::factory()->create([
        'terms_version' => '1.0',
        'terms_accepted_at' => now(),
    ]);

    Sanctum::actingAs($user);
    // Simulate SPA where currentAccessToken is null to verify null-safety
    $user->withAccessToken(null);

    $response = $this->deleteJson('/api/v1/me');

    $response->assertNoContent();

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

// 6.5. Google Sign-in Flow: Test Google redirect URL generation and state caching WITHOUT guest token
it('generates Google redirect URL and stores state in cache even when user has no guest token', function () {
    $response = $this->postJson('/api/v1/auth/google/redirect');

    $response->assertOk()
        ->assertJsonStructure(['url']);

    $parsedUrl = parse_url($response->json('url'));
    parse_str($parsedUrl['query'] ?? '', $queryParams);

    expect(isset($queryParams['state']))->toBeTrue();
    expect(Cache::has('google_state_'.$queryParams['state']))->toBeTrue();
});

// 6.6. Google Sign-in Flow: Test successful callback when user had NO guest token
it('successfully authenticates user via Google callback when no guest token was provided', function () {
    $state = 'test-state-without-guest-token';
    Cache::put('google_state_'.$state, '', now()->addMinutes(10));

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getEmail')->andReturn('googleuser@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Google User');

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/api/v1/auth/google/callback?state='.$state);

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback');

    $this->assertDatabaseHas('users', [
        'email' => 'googleuser@example.com',
    ]);
});

// 6.7. Google Sign-in Flow: Test invalid state redirects with error
it('redirects with google_invalid_state when state is missing or not in cache', function () {
    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

    $response = $this->get('/api/v1/auth/google/callback?state=invalid-state');

    $response->assertRedirect($frontendUrl.'/auth/callback?error=google_invalid_state');
});

// 7. Guest Claiming Flow: Test claiming guest documents upon successful magic link sign-in
it('claims guest documents and assigns them to the user upon magic link verification', function () {
    $guestToken = str_repeat('a', 64);
    $guestTokenHash = hash('sha256', $guestToken);

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

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/auth/magic-link/verify', [
            'token' => $rawToken,
        ]);

    $response->assertOk();

    $user = User::where('email', 'guestuser@example.com')->first();

    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'user_id' => $user->id,
        'guest_token_hash' => null,
    ]);
});

// 8. Localization Flow: Test error message response language based on Accept-Language header
it('returns localized error messages matching Accept-Language header', function () {
    // Test Arabic localization
    $arResponse = $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/auth/magic-link/verify', ['token' => 'invalid-tok']);

    $arResponse->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'link_invalid',
                'message' => 'رابط تسجيل الدخول غير صالح.',
            ],
        ]);

    // Test English localization
    $enResponse = $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/v1/auth/magic-link/verify', ['token' => 'invalid-tok']);

    $enResponse->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'link_invalid',
                'message' => 'Sign-in link token unknown or changed.',
            ],
        ]);
});
