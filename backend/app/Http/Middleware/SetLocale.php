<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If user is authenticated and has a set locale, respect it
        if ($request->user() && in_array($request->user()->locale, ['ar', 'en'])) {
            app()->setLocale($request->user()->locale);
        } else {
            // 2. Parse Accept-Language header
            $header = $request->header('Accept-Language', 'ar');
            $locale = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));
            app()->setLocale(in_array($locale, ['ar', 'en']) ? $locale : 'ar');
        }

        return $next($request);
    }
}
