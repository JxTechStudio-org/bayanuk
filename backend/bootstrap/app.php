<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                
                $code = match ($status) {
                    401 => 'unauthorized',
                    419 => 'csrf_token_mismatch',
                    422 => 'validation_error',
                    400 => 'invalid_guest_token',
                    429 => 'magic_link_limit_reached',
                    default => 'server_error',
                };

                $locale = $request->header('Accept-Language', 'en');
                app()->setLocale($locale);

                $message = __("messages.{$code}") !== "messages.{$code}" 
                    ? __("messages.{$code}") 
                    : ($e->getMessage() ?: __('messages.server_error'));

                $response = [
                    'error' => [
                        'code' => $code,
                        'message' => $message,
                    ]
                ];

                if ($status === 422 && method_exists($e, 'errors')) {
                    $response['error']['details'] = $e->errors();
                }

                return response()->json($response, $status);
            }
        });

    })->create();
