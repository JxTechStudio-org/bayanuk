<?php

use App\Exceptions\ApiException;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------------------------
// Shared error format and HTTP status mapping (Contract Section 4)
// ---------------------------------------------------------------------------------------------

it('returns shared error format with code and message for contract exceptions', function () {
    Route::middleware('api')->get('/api/v1/_test-contract-error', function () {
        throw new ApiException('document_not_found', 404);
    });

    $response = $this->getJson('/api/v1/_test-contract-error');

    $response->assertStatus(404)
        ->assertJsonStructure([
            'error' => [
                'code',
                'message',
            ],
        ])
        ->assertJsonPath('error.code', 'document_not_found');

    expect($response->json('error'))->not->toHaveKey('details');
});

it('formats validation errors with code validation_error and details object (422)', function () {
    $response = $this->postJson('/api/v1/auth/magic-link', ['email' => 'invalid-email']);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'error' => [
                'code',
                'message',
                'details' => ['email'],
            ],
        ])
        ->assertJsonPath('error.code', 'validation_error');
});

it('formats unauthenticated requests as unauthorized (401)', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthorized');
});

it('formats method not allowed requests as method_not_allowed (405)', function () {
    $this->getJson('/api/v1/auth/magic-link/verify')
        ->assertStatus(405)
        ->assertJsonPath('error.code', 'method_not_allowed');
});

it('formats CSRF token mismatches as csrf_token_mismatch (419)', function () {
    Route::middleware('api')->post('/api/v1/_test-csrf-error', function () {
        throw new TokenMismatchException('CSRF token mismatch.');
    });

    $this->postJson('/api/v1/_test-csrf-error')
        ->assertStatus(419)
        ->assertJsonPath('error.code', 'csrf_token_mismatch');
});

it('returns generic server_error (500) for unhandled exceptions without leaking stack traces', function () {
    Route::middleware('api')->get('/api/v1/_test-secret-crash', function () {
        throw new Exception('CRITICAL_POSTGRES_PASSWORD_SECRET_LEAK');
    });

    $response = $this->getJson('/api/v1/_test-secret-crash');

    $response->assertStatus(500)
        ->assertJsonPath('error.code', 'server_error');

    expect($response->getContent())->not->toContain('CRITICAL_POSTGRES_PASSWORD_SECRET_LEAK');
});

// ---------------------------------------------------------------------------------------------
// Localization (Accept-Language header)
// ---------------------------------------------------------------------------------------------

it('returns Arabic error message when Accept-Language is ar or by default', function () {
    $responseAr = $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/auth/magic-link/verify', ['token' => 'invalid-tok']);

    $responseAr->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'link_invalid',
                'message' => 'رابط تسجيل الدخول غير صالح، يرجى طلب رابط جديد.',
            ],
        ]);

    // Default without header is Arabic
    $responseDefault = $this->postJson('/api/v1/auth/magic-link/verify', ['token' => 'invalid-tok']);
    $responseDefault->assertStatus(400)
        ->assertJsonPath('error.message', 'رابط تسجيل الدخول غير صالح، يرجى طلب رابط جديد.');
});

it('returns English error message when Accept-Language is en', function () {
    $responseEn = $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/v1/auth/magic-link/verify', ['token' => 'invalid-tok']);

    $responseEn->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'link_invalid',
                'message' => 'This sign-in link is not valid. Please request a new one.',
            ],
        ]);
});

it('returns Arabic validation messages when Accept-Language is ar', function () {
    $response = $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/auth/magic-link', ['email' => '']);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');

    $emailErrors = $response->json('error.details.email');
    expect($emailErrors[0])->toContain('البريد الإلكتروني');
});

// ---------------------------------------------------------------------------------------------
// Sanctum CSRF Cookie Endpoint
// ---------------------------------------------------------------------------------------------

it('returns 204 and sets XSRF-TOKEN cookie on /sanctum/csrf-cookie', function () {
    $response = $this->get('/sanctum/csrf-cookie');

    $response->assertNoContent();
    $response->assertCookie('XSRF-TOKEN');
});
