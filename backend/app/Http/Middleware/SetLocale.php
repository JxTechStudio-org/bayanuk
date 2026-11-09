<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The interface language comes from the Accept-Language header only
 * (Integration Contract section 4): `ar` or `en`, Arabic when nothing supported is sent.
 */
class SetLocale
{
    private const SUPPORTED = ['ar', 'en'];

    private const DEFAULT = 'ar';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        // getLanguages() is already ordered by the q-values of the header.
        foreach ($request->getLanguages() as $language) {
            $code = strtolower(substr($language, 0, 2));

            if (in_array($code, self::SUPPORTED, true)) {
                return $code;
            }
        }

        return self::DEFAULT;
    }
}
