<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\LoginToken;
use App\Models\User;
use App\Services\Auth\GuestDocumentClaimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Resend\Laravel\Facades\Resend;

class MagicLinkController extends Controller
{
    /** Link requests allowed per minute, for one IP and for one email address. */
    private const MAX_REQUESTS_PER_MINUTE = 5;

    public function __construct(private readonly GuestDocumentClaimer $guests) {}

    // POST /api/v1/auth/magic-link: email a single-use sign-in link.
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = mb_strtolower($validated['email']);

        $limiterKeys = [
            'magic-link:ip:'.sha1((string) $request->ip()),
            'magic-link:email:'.sha1($email),
        ];

        foreach ($limiterKeys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS_PER_MINUTE)) {
                throw new ApiException('magic_link_limit_reached', 429);
            }
        }

        foreach ($limiterKeys as $key) {
            RateLimiter::hit($key, 60);
        }

        // Only the SHA-256 hash is stored; the raw token only exists inside the email.
        $rawToken = Str::random(64);

        $loginToken = LoginToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);

        $verificationUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/').'/auth/verify?token='.$rawToken;

        try {
            Resend::emails()->send([
                'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                'to' => [$email],
                'subject' => __('messages.magic_link_subject'),
                'html' => view('emails.magic-link', ['url' => $verificationUrl])->render(),
            ]);
        } catch (\Throwable $e) {
            // A link nobody received must not stay usable, and the caller must not think it was sent.
            $loginToken->delete();
            report($e);

            throw new ApiException('server_error', 500);
        }

        return response()->json([
            'message' => __('messages.magic_link_sent'),
        ]);
    }

    // POST /api/v1/auth/magic-link/verify: exchange the link token for a session and claim guest documents.
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        // Checked first, so a bad guest token never uses up the link.
        $guestToken = $this->guests->tokenFromRequest($request);

        $tokenHash = hash('sha256', $request->string('token')->toString());

        $user = DB::transaction(function () use ($tokenHash, $guestToken): User {
            // The row lock makes the link single-use even when two requests arrive together.
            $loginToken = LoginToken::where('token_hash', $tokenHash)->lockForUpdate()->first();

            if (! $loginToken) {
                throw new ApiException('link_invalid', 400);
            }

            if ($loginToken->used_at) {
                throw new ApiException('link_used', 410);
            }

            if ($loginToken->expires_at->isPast()) {
                throw new ApiException('link_expired', 410);
            }

            $loginToken->update(['used_at' => now()]);

            $user = User::firstOrCreate(
                ['email' => $loginToken->email],
                [
                    'terms_version' => '1.0',
                    'terms_accepted_at' => now(),
                ]
            );

            if (! $user->email_verified_at) {
                $user->update(['email_verified_at' => now()]);
            }

            $this->guests->claim($user, $guestToken);

            return $user;
        });

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => __('messages.signed_in'),
        ]);
    }
}
