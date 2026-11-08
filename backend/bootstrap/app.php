<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // 1. Explicit contract ApiException
                if ($e instanceof ApiException) {
                    $response = [
                        'error' => [
                            'code' => $e->errorCode,
                            'message' => $e->getMessage(),
                        ],
                    ];
                    if (! empty($e->details)) {
                        $response['error']['details'] = $e->details;
                    }

                    return response()->json($response, $e->statusCode);
                }

                // 2. Validation Exception (422)
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'error' => [
                            'code' => 'validation_error',
                            'message' => __('messages.validation_error'),
                            'details' => $e->errors(),
                        ],
                    ], 422);
                }

                // 3. Authentication Exception (401)
                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'error' => [
                            'code' => 'unauthorized',
                            'message' => __('messages.unauthorized'),
                        ],
                    ], 401);
                }

                // 4. CSRF Token Mismatch (419)
                if ($e instanceof TokenMismatchException) {
                    return response()->json([
                        'error' => [
                            'code' => 'csrf_token_mismatch',
                            'message' => __('messages.csrf_token_mismatch'),
                        ],
                    ], 419);
                }

                // 5. Model Not Found / Route Not Found (404)
                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'error' => [
                            'code' => 'document_not_found',
                            'message' => __('messages.document_not_found'),
                        ],
                    ], 404);
                }

                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                // 6. Rate Limiting (429)
                if ($status === 429) {
                    return response()->json([
                        'error' => [
                            'code' => 'too_many_requests',
                            'message' => __('messages.too_many_requests'),
                        ],
                    ], 429);
                }

                // 7. Generic fallback for other 4xx/5xx exceptions
                $code = $status === 500 ? 'server_error' : 'server_error';
                $message = $status === 500 ? __('messages.server_error') : ($e->getMessage() ?: __('messages.server_error'));

                return response()->json([
                    'error' => [
                        'code' => $code,
                        'message' => $message,
                    ],
                ], $status >= 400 && $status < 600 ? $status : 500);
            }
        });

    })->create();
