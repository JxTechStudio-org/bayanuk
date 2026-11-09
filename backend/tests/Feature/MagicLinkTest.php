<?php

use App\Models\Document;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\FakeResend;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function guestDocument(string $guestToken, array $overrides = []): Document
{
    return Document::create(array_merge([
        'guest_token_hash' => hash('sha256', $guestToken),
        'user_id' => null,
        'original_filename' => 'contract.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', uniqid('', true)),
        'storage_key' => 'guest/'.uniqid('', true).'.pdf',
        'status' => 'ready',
        'expires_at' => now()->addDay(),
    ], $overrides));
}

function expiredOrUsedLink(array $attributes): string
{
    $raw = Str::random(64);

    LoginToken::create(array_merge([
        'email' => 'someone@example.com',
        'token_hash' => hash('sha256', $raw),
        'expires_at' => now()->addMinutes(15),
    ], $attributes));

    return $raw;
}

// ---------------------------------------------------------------------------------------------
// Requesting a link: POST /auth/magic-link
// ---------------------------------------------------------------------------------------------

it('sends a real email through Resend with a working single-use link', function () {
    $resend = FakeResend::install();

    $this->postJson('/api/v1/auth/magic-link', ['email' => 'User@Example.com'])
        ->assertOk()
        ->assertJson(['message' => 'Magic link sent successfully.']);

    // One email, to the right (lower-cased) address, with a subject and a link to the frontend page.
    expect($resend->sent)->toHaveCount(1);
    $email = $resend->sent[0];
    expect($email['to'])->toBe(['user@example.com'])
        ->and($email['subject'])->not->toBeEmpty()
        ->and($email['from'])->toContain('@')
        ->and($email['html'])->toContain(config('app.frontend_url').'/auth/verify?token=');

    // Only the hash of the token in the email is stored, never the token itself.
    $rawToken = $resend->lastToken();
    expect($rawToken)->toHaveLength(64);

    $stored = LoginToken::where('email', 'user@example.com')->sole();
    expect($stored->token_hash)->toBe(hash('sha256', $rawToken))
        ->and($stored->token_hash)->not->toBe($rawToken)
        ->and($stored->used_at)->toBeNull()
        ->and($stored->expires_at->isFuture())->toBeTrue()
        ->and($stored->expires_at->lessThanOrEqualTo(now()->addMinutes(15)))->toBeTrue();

    // The link from the email really signs the user in, and only once.
    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $rawToken])->assertOk();
    $this->assertAuthenticated('web');
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('email', 'user@example.com');

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $rawToken])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'link_used');
});

it('writes the email in the language of the interface', function (string $locale, string $dir, string $subject) {
    $resend = FakeResend::install();

    $this->withHeader('Accept-Language', $locale)
        ->postJson('/api/v1/auth/magic-link', ['email' => 'user@example.com'])
        ->assertOk();

    expect($resend->sent[0]['subject'])->toBe($subject)
        ->and($resend->sent[0]['html'])->toContain('dir="'.$dir.'"');
})->with([
    'arabic' => ['ar', 'rtl', 'تسجيل الدخول إلى بيانك'],
    'english' => ['en', 'ltr', 'Sign in to Bayanuk'],
]);

it('does not report success when the email cannot be sent', function () {
    FakeResend::install(new RuntimeException('Resend is down: secret-internal-detail'));

    $response = $this->postJson('/api/v1/auth/magic-link', ['email' => 'user@example.com']);

    $response->assertStatus(500)->assertJsonPath('error.code', 'server_error');
    expect($response->getContent())->not->toContain('secret-internal-detail');

    // A link nobody received must not stay usable.
    $this->assertDatabaseMissing('login_tokens', ['email' => 'user@example.com']);
});

it('rejects a missing or invalid email with validation_error (422)', function (array $payload) {
    $resend = FakeResend::install();

    $this->postJson('/api/v1/auth/magic-link', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['email']]]);

    expect($resend->sent)->toBeEmpty();
    $this->assertDatabaseCount('login_tokens', 0);
})->with([
    'missing' => [[]],
    'not an email' => [['email' => 'not-an-email']],
    'too long' => [['email' => str_repeat('a', 250).'@example.com']],
]);

it('limits link requests per IP with magic_link_limit_reached (429)', function () {
    $resend = FakeResend::install();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/magic-link', ['email' => "person{$i}@example.com"])->assertOk();
    }

    $this->postJson('/api/v1/auth/magic-link', ['email' => 'person6@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'magic_link_limit_reached');

    expect($resend->sent)->toHaveCount(5);
    $this->assertDatabaseMissing('login_tokens', ['email' => 'person6@example.com']);
});

it('limits link requests per email address even when they come from different IPs', function () {
    $resend = FakeResend::install();

    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
            ->postJson('/api/v1/auth/magic-link', ['email' => 'victim@example.com'])
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->postJson('/api/v1/auth/magic-link', ['email' => 'Victim@Example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'magic_link_limit_reached');

    expect($resend->sent)->toHaveCount(5);
});

it('allows link requests again once the minute has passed', function () {
    FakeResend::install();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/magic-link', ['email' => 'again@example.com'])->assertOk();
    }
    $this->postJson('/api/v1/auth/magic-link', ['email' => 'again@example.com'])->assertStatus(429);

    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/magic-link', ['email' => 'again@example.com'])->assertOk();
});

it('stores a hash of the IP, not the IP', function () {
    FakeResend::install();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson('/api/v1/auth/magic-link', ['email' => 'user@example.com'])
        ->assertOk();

    $ipHash = LoginToken::where('email', 'user@example.com')->sole()->ip_hash;

    expect($ipHash)->toHaveLength(64)
        ->and($ipHash)->not->toBe(hash('sha256', '203.0.113.7'));
});

// ---------------------------------------------------------------------------------------------
// Using a link: POST /auth/magic-link/verify
// ---------------------------------------------------------------------------------------------

it('accepts POST only on verify, so email scanners cannot use up the link', function () {
    $raw = expiredOrUsedLink([]);

    $this->getJson('/api/v1/auth/magic-link/verify?token='.$raw)
        ->assertStatus(405)
        ->assertJsonPath('error.code', 'method_not_allowed');

    expect(LoginToken::sole()->used_at)->toBeNull();
});

it('returns link_expired (410) for an expired link and does not sign in', function () {
    $raw = expiredOrUsedLink(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'link_expired');

    $this->assertGuest('web');
    $this->assertDatabaseMissing('users', ['email' => 'someone@example.com']);
});

it('returns link_used (410) for a link that was already used', function () {
    $raw = expiredOrUsedLink(['used_at' => now()->subMinutes(2)]);

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'link_used');

    $this->assertDatabaseMissing('users', ['email' => 'someone@example.com']);
});

it('returns link_invalid (400) for an unknown or changed token', function () {
    $raw = expiredOrUsedLink([]);

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => 'never-issued'])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'link_invalid');

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw.'x'])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'link_invalid');

    expect(LoginToken::sole()->used_at)->toBeNull();
});

it('requires a token on verify (422)', function () {
    $this->postJson('/api/v1/auth/magic-link/verify')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['token']]]);
});

it('creates the account and signs the user in', function () {
    $rawToken = Str::random(64);
    LoginToken::create([
        'email' => 'new.user@example.com',
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $rawToken])
        ->assertOk()
        ->assertJson(['message' => 'Successfully authenticated.']);

    // A new, verified account, with the email stored in lower case.
    $user = User::where('email', 'new.user@example.com')->sole();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->terms_version)->toBe('1.0');

    $this->assertAuthenticatedAs($user, 'web');
});

it('signs in an existing user without creating a second account', function () {
    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'email_verified_at' => now()->subYear(),
        'terms_version' => '1.0',
        'terms_accepted_at' => now()->subYear(),
    ]);
    $verifiedAt = $existing->email_verified_at;

    $rawToken = Str::random(64);
    LoginToken::create([
        'email' => 'existing@example.com',
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $rawToken])->assertOk();

    expect(User::where('email', 'existing@example.com')->count())->toBe(1)
        ->and($existing->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();

    $this->assertAuthenticatedAs($existing, 'web');
});

// ---------------------------------------------------------------------------------------------
// Claiming guest documents (X-Guest-Token header on verify)
// ---------------------------------------------------------------------------------------------

it('moves only the signing-in guest\'s documents to the account', function () {
    $guestToken = str_repeat('a', 64);
    $otherGuestToken = str_repeat('b', 64);

    $mine = guestDocument($guestToken);
    $alsoMine = guestDocument($guestToken);
    $someoneElses = guestDocument($otherGuestToken);
    $owner = User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]);
    $ownedByUser = guestDocument($guestToken, ['user_id' => $owner->id, 'guest_token_hash' => null]);

    $rawToken = Str::random(64);
    LoginToken::create([
        'email' => 'guest.user@example.com',
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/auth/magic-link/verify', ['token' => $rawToken])
        ->assertOk();

    $user = User::where('email', 'guest.user@example.com')->sole();

    foreach ([$mine, $alsoMine] as $document) {
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'user_id' => $user->id, 'guest_token_hash' => null]);
    }

    // Another guest's document and another user's document are untouched.
    $this->assertDatabaseHas('documents', ['id' => $someoneElses->id, 'user_id' => null, 'guest_token_hash' => hash('sha256', $otherGuestToken)]);
    $this->assertDatabaseHas('documents', ['id' => $ownedByUser->id, 'user_id' => $owner->id]);
});

it('signs in without a guest token and claims nothing', function () {
    $document = guestDocument(str_repeat('a', 64));

    $rawToken = Str::random(64);
    LoginToken::create([
        'email' => 'no.guest@example.com',
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $rawToken])->assertOk();

    $this->assertDatabaseHas('documents', ['id' => $document->id, 'user_id' => null]);
});

it('refuses a malformed guest token with invalid_guest_token and keeps the link usable', function () {
    $raw = expiredOrUsedLink([]);

    $this->withHeader('X-Guest-Token', 'malformed')
        ->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'invalid_guest_token');

    expect(LoginToken::sole()->used_at)->toBeNull();
    $this->assertGuest('web');

    // The frontend drops the bad token and tries the same link again without it.
    $this->flushHeaders();
    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw])->assertOk();
    $this->assertAuthenticated('web');
});

it('does not lose the link or the guest documents when something fails half way', function () {
    $guestToken = str_repeat('a', 64);
    $document = guestDocument($guestToken);
    $raw = expiredOrUsedLink(['email' => 'half@example.com']);

    // Make the claim fail: make the email too long for the database column
    LoginToken::sole()->update(['email' => 'toolong@example.com']);
    User::creating(function () {
        throw new RuntimeException('Simulated crash during user creation');
    });

    $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw])
        ->assertStatus(500)
        ->assertJsonPath('error.code', 'server_error');

    expect(LoginToken::sole()->used_at)->toBeNull();
    $this->assertDatabaseHas('documents', ['id' => $document->id, 'user_id' => null]);
});

it('uses the link only once even when the same request is repeated', function () {
    $raw = expiredOrUsedLink([]);

    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw])->assertOk();
    $this->postJson('/api/v1/auth/magic-link/verify', ['token' => $raw])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'link_used');

    expect(User::where('email', 'someone@example.com')->count())->toBe(1);
});

afterEach(function () {
    RateLimiter::clear('magic-link');
});
