<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class AuthController extends Controller
{
    // Start Google Sign-in flow: saves the guest token with state and returns the Google URL.
    public function googleRedirect(Request $request): JsonResponse
    {
        $guestToken = $request->header('X-Guest-Token');

        $state = Str::random(40);

        // Store the guest token against the state in cache for 10 minutes if provided
        if ($guestToken) {
            Cache::put('google_state_'.$state, $guestToken, now()->addMinutes(10));
        }

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');
        $url = $driver->stateless()->with(['state' => $state])->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    // Handle Google Sign-in callback, validate state, and authenticate user.

    public function googleCallback(Request $request): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // 1. Check if the user cancelled the login on Google's side
        if ($request->has('error') && $request->error === 'access_denied') {
            return redirect()->to($frontendUrl.'/auth/callback?error=google_cancelled');
        }

        $state = $request->input('state');

        // 2. Validate state presence and validity
        if (! $state || ! Cache::has('google_state_'.$state)) {
            return redirect()->to($frontendUrl.'/auth/callback?error=google_invalid_state');
        }

        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->to($frontendUrl.'/auth/callback?error=google_sign_in_failed');
        }

        if (! $googleUser->getEmail()) {
            return redirect()->to($frontendUrl.'/auth/callback?error=google_sign_in_failed');
        }

        // 3. Find or create the user account based on Google's email
        $user = User::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'email_verified_at' => now(),
                'terms_version' => '1.0',
                'terms_accepted_at' => now(),
            ]
        );

        if (! $user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        // 4. Authenticate the user session
        Auth::login($user);

        // 5. Retrieve and claim guest documents using the saved guest token from cache
        $guestToken = Cache::pull('google_state_'.$state);
        if ($guestToken) {
            $guestTokenHash = hash('sha256', $guestToken);
            Document::where('guest_token_hash', $guestTokenHash)
                ->whereNull('user_id')
                ->update([
                    'user_id' => $user->id,
                    'guest_token_hash' => null,
                ]);
        }

        // 6. Redirect to frontend callback page on success
        return redirect()->to($frontendUrl.'/auth/callback');
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request): JsonResponse
    {
        // Invalidate session and clear token for Sanctum SPA
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Successfully logged out.',
        ], 200);
    }
}
