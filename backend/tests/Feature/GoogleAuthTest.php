<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function mockGoogleUser(?string $email = 'google.user@example.com', ?string $id = 'google-id-12345', string $name = 'Google User'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('with')->andReturnSelf();
    $provider->shouldReceive('redirect->getTargetUrl')->andReturn('https://accounts.google.com/o/oauth2/auth?state=mocked');
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

// ---------------------------------------------------------------------------------------------
// Starting Google flow: POST /api/v1/auth/google/redirect
// ---------------------------------------------------------------------------------------------

it('generates a Google redirect URL and stores state without guest token', function () {
    mockGoogleUser();

    $response = $this->postJson('/api/v1/auth/google/redirect');

    $response->assertOk()
        ->assertJsonStructure(['url'])
        ->assertCookie('google_oauth_binding');

    $url = $response->json('url');
    expect($url)->toContain('accounts.google.com');

    $bindingCookie = $response->getCookie('google_oauth_binding')->getValue();
    expect($bindingCookie)->not->toBeEmpty();
});

it('saves the guest token with the state when X-Guest-Token is provided', function () {
    mockGoogleUser();
    $guestToken = str_repeat('a', 64);

    $response = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/auth/google/redirect');

    $response->assertOk();
});

it('rejects a malformed guest token on Google redirect with invalid_guest_token (400)', function () {
    $this->withHeader('X-Guest-Token', 'malformed-token')
        ->postJson('/api/v1/auth/google/redirect')
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'invalid_guest_token');
});

// ---------------------------------------------------------------------------------------------
// Google callback: GET /api/v1/auth/google/callback
// ---------------------------------------------------------------------------------------------

it('redirects with google_cancelled when user cancels on Google side', function () {
    $state = Str::random(40);
    $binding = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => null,
        'binding' => hash('sha256', $binding),
    ], now()->addMinutes(10));

    $response = $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state.'&error=access_denied');

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback?error=google_cancelled');
    $this->assertGuest('web');
});

it('redirects with google_invalid_state when state is unknown or expired', function () {
    $response = $this->get('/api/v1/auth/google/callback?state=unknown-state');

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback?error=google_invalid_state');
});

it('redirects with google_invalid_state when binding cookie does not match', function () {
    $state = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => null,
        'binding' => hash('sha256', 'correct-binding'),
    ], now()->addMinutes(10));

    $response = $this->withCookie('google_oauth_binding', 'wrong-binding')
        ->get('/api/v1/auth/google/callback?state='.$state);

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback?error=google_invalid_state');
});

it('redirects with google_sign_in_failed when Google driver throws an exception', function () {
    $state = Str::random(40);
    $binding = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => null,
        'binding' => hash('sha256', $binding),
    ], now()->addMinutes(10));

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless->user')->andThrow(new RuntimeException('Network failure'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state);

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback?error=google_sign_in_failed');
    $this->assertGuest('web');
});

it('redirects with google_sign_in_failed when Google returns no email', function () {
    $state = Str::random(40);
    $binding = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => null,
        'binding' => hash('sha256', $binding),
    ], now()->addMinutes(10));

    mockGoogleUser(email: null);

    $response = $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state);

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback?error=google_sign_in_failed');
});

it('creates a new user and authenticates successfully on valid callback', function () {
    $state = Str::random(40);
    $binding = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => null,
        'binding' => hash('sha256', $binding),
    ], now()->addMinutes(10));

    mockGoogleUser(email: 'newgoogle@example.com', id: 'gid-999', name: 'Google Person');

    $response = $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state);

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback');

    $user = User::where('email', 'newgoogle@example.com')->sole();
    expect($user->google_id)->toBe('gid-999')
        ->and($user->name)->toBe('Google Person')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->terms_version)->toBe('1.0');

    $this->assertAuthenticatedAs($user, 'web');
});

it('links existing user by email and sets google_id', function () {
    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
        'terms_version' => '1.0',
        'terms_accepted_at' => now(),
    ]);

    $state = Str::random(40);
    $binding = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => null,
        'binding' => hash('sha256', $binding),
    ], now()->addMinutes(10));

    mockGoogleUser(email: 'existing@example.com', id: 'gid-linked');

    $response = $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state);

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback');

    expect($existing->fresh()->google_id)->toBe('gid-linked');
    $this->assertAuthenticatedAs($existing, 'web');
});

it('claims guest documents on Google sign-in using cached guest token', function () {
    $guestToken = str_repeat('c', 64);
    $guestDoc = Document::create([
        'guest_token_hash' => hash('sha256', $guestToken),
        'user_id' => null,
        'original_filename' => 'guest-offer.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'sha256' => hash('sha256', 'file-offer'),
        'storage_key' => 'guest/offer.pdf',
        'status' => 'ready',
        'expires_at' => now()->addDay(),
    ]);

    $state = Str::random(40);
    $binding = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => $guestToken,
        'binding' => hash('sha256', $binding),
    ], now()->addMinutes(10));

    mockGoogleUser(email: 'claimed@example.com', id: 'gid-claim');

    $response = $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state);

    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $response->assertRedirect($frontendUrl.'/auth/callback');

    $user = User::where('email', 'claimed@example.com')->sole();

    expect($guestDoc->fresh()->user_id)->toBe($user->id)
        ->and($guestDoc->fresh()->guest_token_hash)->toBeNull();
});

it('consumes state immediately so it cannot be replayed', function () {
    $state = Str::random(40);
    $binding = Str::random(40);
    Cache::put('google_state_'.$state, [
        'guest_token' => null,
        'binding' => hash('sha256', $binding),
    ], now()->addMinutes(10));

    mockGoogleUser(email: 'replay@example.com', id: 'gid-replay');

    // First attempt succeeds
    $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state)
        ->assertRedirect(config('app.frontend_url').'/auth/callback');

    // Second attempt fails with invalid state
    $this->withCookie('google_oauth_binding', $binding)
        ->get('/api/v1/auth/google/callback?state='.$state)
        ->assertRedirect(config('app.frontend_url').'/auth/callback?error=google_invalid_state');
});
