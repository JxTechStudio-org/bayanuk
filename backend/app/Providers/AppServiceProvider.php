<?php

namespace App\Providers;

use App\Services\Auth\GuestDocumentClaimer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Technical Plan section 8: rate limits per IP, guest token, and user.
        RateLimiter::for('api', function (Request $request) {
            $perMinute = (int) config('bayanuk.api_rate_limit_per_minute', 60);

            $tooManyRequests = function (Request $request, array $headers) {
                return response()->json([
                    'error' => [
                        'code' => 'too_many_requests',
                        'message' => __('messages.too_many_requests'),
                    ],
                ], 429, $headers);
            };

            $limits = [
                Limit::perMinute($perMinute)->by('ip:'.$request->ip())->response($tooManyRequests),
            ];

            $user = $request->user();
            $guestToken = $request->header('X-Guest-Token');

            if ($user) {
                $limits[] = Limit::perMinute($perMinute)->by('user:'.$user->getAuthIdentifier())->response($tooManyRequests);
            } elseif (is_string($guestToken) && preg_match(GuestDocumentClaimer::FORMAT, $guestToken)) {
                $limits[] = Limit::perMinute($perMinute)->by('guest:'.hash('sha256', $guestToken))->response($tooManyRequests);
            }

            return $limits;
        });
    }
}
