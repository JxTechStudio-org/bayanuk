<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Resend\Laravel\Facades\Resend;

class MagicLinkController extends Controller
{
    // Send a magic link to the user's email with rate limiting.
    public function send(Request $request): JsonResponse
    {
        // 1. Validate the incoming email input
        $request->validate([
            'email' => 'required|email',
        ]);

        // 2. Apply Rate Limiting (Max 5 attempts per minute per IP)
        $rateKey = 'magic-link-'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return response()->json([
                'error' => [
                    'code' => 'magic_link_limit_reached',
                    'message' => __('messages.magic_link_limit_reached'),
                ],
            ], 429);
        }

        RateLimiter::hit($rateKey, 60);

        // 3. Generate a raw token and store its SHA-256 hash in the database
        $rawToken = Str::random(64);

        LoginToken::create([
            'email' => strtolower($request->email),
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
            'ip_hash' => hash('sha256', $request->ip()),
        ]);

        // 4. Send the actual email via Resend SDK
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $verificationUrl = $frontendUrl.'/auth/verify?token='.$rawToken;

        try {
            Resend::emails()->send([
                'from' => config('mail.from.name', 'Bayanuk').' <'.config('mail.from.address', 'no-reply@bayanuk.test').'>',
                'to' => [$request->email],
                'subject' => __('messages.magic_link_subject') ?: 'تسجيل الدخول إلى بيانك | Login to Bayanuk',
                'html' => view('emails.magic-link', ['url' => $verificationUrl])->render(),
            ]);
        } catch (\Throwable $e) {
            // Graceful fallback for local development & testing when RESEND_API_KEY is not set
            if (app()->environment(['local', 'testing']) && empty(config('services.resend.key'))) {
                Log::info("Magic link generated for {$request->email}: {$verificationUrl}");
            } else {
                throw $e;
            }
        }

        return response()->json([
            'message' => 'Magic link sent successfully.',
        ]);

    }

    // Verify the magic link token and authenticate the user.
    public function verify(Request $request): JsonResponse
    {
        // 1. Validate that the token is present in the request
        $request->validate([
            'token' => 'required|string',
        ]);

        // 2. Hash the provided token to match it against database records
        $tokenHash = hash('sha256', $request->token);

        $loginToken = LoginToken::where('token_hash', $tokenHash)->first();

        // Error: Token does not exist (Invalid Link - 400)
        if (! $loginToken) {
            return response()->json([
                'error' => [
                    'code' => 'link_invalid',
                    'message' => __('messages.link_invalid'),
                ],
            ], 400);
        }

        // Error: Token has already been used (Used Link - 410)
        if ($loginToken->used_at) {
            return response()->json([
                'error' => [
                    'code' => 'link_used',
                    'message' => __('messages.link_used'),
                ],
            ], 410);
        }

        // Error: Token expiration time has passed (Expired Link - 410)
        if ($loginToken->expires_at->isPast()) {
            return response()->json([
                'error' => [
                    'code' => 'link_expired',
                    'message' => __('messages.link_expired'),
                ],
            ], 410);
        }

        // 3. Mark the token as used
        $loginToken->update(['used_at' => now()]);

        // 4. Find or create the user account based on the token's email
        $user = User::firstOrCreate(
            ['email' => $loginToken->email],
            [
                'email_verified_at' => now(),
                'terms_version' => '1.0',
                'terms_accepted_at' => now(),
            ]
        );

        if (! $user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        // Authenticate the user session
        auth()->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // 5. Claim guest documents if an X-Guest-Token header is provided
        $guestToken = $request->header('X-Guest-Token');
        if ($guestToken) {
            $guestTokenHash = hash('sha256', $guestToken);
            Document::where('guest_token_hash', $guestTokenHash)
                ->whereNull('user_id')
                ->update([
                    'user_id' => $user->id,
                    'guest_token_hash' => null,
                ]);
        }

        return response()->json([
            'message' => 'Successfully authenticated.',
        ]);
    }
}
