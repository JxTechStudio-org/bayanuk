<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureValidGuestToken;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->append(SetLocale::class);
        $middleware->alias([
            'guest.token' => EnsureValidGuestToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Every general error has code and message only (Integration Contract section 4).
            $error = fn (string $code, int $status, ?string $message = null) => response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $message ?? __("messages.{$code}"),
                ],
            ], $status);

            // 1. Errors raised on purpose by the application carry their own code and status.
            if ($e instanceof ApiException) {
                return $error($e->errorCode, $e->statusCode, $e->getMessage());
            }

            // 2. Errors raised by the framework are mapped by what they are.
            // details is used strictly and only by validation_error (Integration Contract section 4).
            if ($e instanceof ValidationException) {
                return response()->json([
                    'error' => [
                        'code' => 'validation_error',
                        'message' => __('messages.validation_error'),
                        'details' => $e->errors(),
                    ],
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return $error('unauthorized', 401);
            }

            // Laravel turns TokenMismatchException into a plain HttpException(419) before this point.
            if ($e instanceof TokenMismatchException || $e->getPrevious() instanceof TokenMismatchException) {
                return $error('csrf_token_mismatch', 419);
            }

            if ($e instanceof TooManyRequestsHttpException || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 429)) {
                return $error('too_many_requests', 429);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return $error('method_not_allowed', 405);
            }

            if ($e instanceof NotFoundHttpException) {
                return $error('document_not_found', 404);
            }

            // 3. Anything else never shows internal details.
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return $error('server_error', $status >= 400 && $status < 600 ? $status : 500);
        });
    })->create();
