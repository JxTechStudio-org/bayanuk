<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Integration Contract section 5: the backend generates the token and returns it once.
it('issues a random 64-character guest token and creates no document', function () {
    $first = $this->postJson('/api/v1/guest-token')
        ->assertStatus(201)
        ->assertJsonStructure(['token'])
        ->json('token');

    $second = $this->postJson('/api/v1/guest-token')->assertStatus(201)->json('token');

    expect($first)->toMatch('/^[A-Za-z0-9]{64}$/')
        ->and($second)->toMatch('/^[A-Za-z0-9]{64}$/')
        ->and($second)->not->toBe($first);

    $this->assertDatabaseCount('documents', 0);
});

// The frontend deletes a rejected token and asks again: the issuing endpoint must never block that.
it('still issues a token when the request carries an old or malformed guest token', function () {
    $this->withHeader('X-Guest-Token', 'malformed')
        ->postJson('/api/v1/guest-token')
        ->assertStatus(201)
        ->assertJsonStructure(['token']);
});

it('does not accept GET on the guest token endpoint', function () {
    $this->getJson('/api/v1/guest-token')
        ->assertStatus(405)
        ->assertJsonPath('error.code', 'method_not_allowed');
});

// Checklist: a missing or invalid guest token returns invalid_guest_token (400) on guest requests.
// The documents task attaches this middleware to its routes; here it is exercised on a probe route.
describe('guest.token middleware', function () {
    beforeEach(function () {
        Route::middleware(['api', 'guest.token'])->get('/api/v1/_guest-probe', fn () => ['ok' => true]);
    });

    it('refuses a request without a guest token', function () {
        $this->getJson('/api/v1/_guest-probe')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_guest_token');
    });

    it('refuses a guest token in the wrong format', function (string $token) {
        $this->withHeader('X-Guest-Token', $token)
            ->getJson('/api/v1/_guest-probe')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_guest_token');
    })->with([
        'too short' => 'abc',
        'too long' => str_repeat('a', 65),
        'bad characters' => str_repeat('a', 63).'-',
    ]);

    it('lets a valid guest token through', function () {
        $token = $this->postJson('/api/v1/guest-token')->json('token');

        $this->withHeader('X-Guest-Token', $token)
            ->getJson('/api/v1/_guest-probe')
            ->assertOk();
    });

    it('does not ask a signed-in user for a guest token', function () {
        Sanctum::actingAs(User::factory()->create(['terms_version' => '1.0', 'terms_accepted_at' => now()]));

        $this->getJson('/api/v1/_guest-probe')->assertOk();
    });
});
