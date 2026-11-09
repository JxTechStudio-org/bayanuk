<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\Auth\GuestDocumentClaimer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest access to document and question routes (Integration Contract section 5).
 *
 * A signed-in user passes without a guest token. Everyone else must send a valid
 * X-Guest-Token header, otherwise the request is refused with invalid_guest_token (400).
 */
class EnsureValidGuestToken
{
    public function __construct(private readonly GuestDocumentClaimer $guests) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        if (! $this->guests->isValid($request->header(GuestDocumentClaimer::HEADER))) {
            throw new ApiException('invalid_guest_token', 400);
        }

        return $next($request);
    }
}
