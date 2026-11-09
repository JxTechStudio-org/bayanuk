<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GuestDocumentClaimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class AuthController extends Controller
{
    private const STATE_CACHE_PREFIX = 'google_state_';

    private const BINDING_COOKIE = 'google_oauth_binding';

    private const STATE_MINUTES = 10;

    public function __construct(private readonly GuestDocumentClaimer $guests) {}

    // POST /api/v1/auth/google/redirect: save the guest token with a random state and return the Google URL.
    public function googleRedirect(Request $request): JsonResponse
    {
        // A malformed guest token is refused here; a missing one is fine (signing in without a guest token).
        $guestToken = $this->guests->tokenFromRequest($request);

        $state = Str::random(40);
        $binding = Str::random(40);

        // The state is only good for one callback, and only for the browser that started the sign-in.
        Cache::put(self::STATE_CACHE_PREFIX.$state, [
            'guest_token' => $guestToken,
            'binding' => hash('sha256', $binding),
        ], now()->addMinutes(self::STATE_MINUTES));

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');
        $url = $driver->stateless()->with(['state' => $state])->redirect()->getTargetUrl();

        return response()->json(['url' => $url])->cookie(
            self::BINDING_COOKIE, $binding, self::STATE_MINUTES, '/', null, $request->isSecure(), true, false, 'lax'
        );
    }

    // GET /api/v1/auth/google/callback: check the state, sign the user in, claim guest documents,
    // and redirect to the frontend with success or an error code.
    public function googleCallback(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $callbackUrl = $frontendUrl.'/auth/callback';

        // The binding cookie is single-use too: whatever happens next, the browser drops it.
        $redirect = fn (string $error = '') => redirect()->to($error === '' ? $callbackUrl : $callbackUrl.'?error='.$error)
            ->withCookie(cookie()->forget(self::BINDING_COOKIE));

        // 1. The state is consumed first, so it can never be used twice.
        $state = $request->query('state');
        $saved = is_string($state) && $state !== ''
            ? Cache::pull(self::STATE_CACHE_PREFIX.$state)
            : null;

        $binding = $request->cookie(self::BINDING_COOKIE);

        if (! is_array($saved)
            || ! is_string($binding)
            || ! hash_equals((string) $saved['binding'], hash('sha256', $binding))) {
            return $redirect('google_invalid_state');
        }

        // 2. The user cancelled, or Google refused.
        if ($request->query->has('error')) {
            return $redirect($request->query('error') === 'access_denied' ? 'google_cancelled' : 'google_sign_in_failed');
        }

        // 3. Ask Google who signed in.
        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();
        } catch (\Throwable) {
            return $redirect('google_sign_in_failed');
        }

        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();

        if (! $email || ! $googleId) {
            return $redirect('google_sign_in_failed');
        }

        // 4. Find or create the account, and claim the guest documents.
        $guestToken = is_string($saved['guest_token'] ?? null) ? $saved['guest_token'] : null;

        $user = DB::transaction(function () use ($googleUser, $email, $googleId, $guestToken): User {
            $user = User::where('google_id', (string) $googleId)->first()
                ?? User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $googleUser->getName(),
                        'terms_version' => '1.0',
                        'terms_accepted_at' => now(),
                    ]
                );

            $user->forceFill([
                'google_id' => $user->google_id ?: (string) $googleId,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            $this->guests->claim($user, $guestToken);

            return $user;
        });

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $redirect();
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => __('messages.signed_out'),
        ]);
    }
}
