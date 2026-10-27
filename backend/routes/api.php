<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GuestTokenController;
use App\Http\Controllers\Api\V1\DocumentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'bayanuk-api',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // temporary: to test voyage api key
    Route::get('/test-voyage', function () {
        try {
            $response = \Illuminate\Support\Facades\Http::withToken(config('services.voyage.key'))
                ->post('https://api.voyageai.com/v1/embeddings', [
                    'input' => ['testing voyage'],
                    'model' => config('services.voyage.model'),
                ]);

            return response()->json([
                'status' => $response->successful() ? 'success' : 'error',
                'status_code' => $response->status(),
                'dimensions_count' => isset($response->json()['data'][0]['embedding']) 
                    ? count($response->json()['data'][0]['embedding']) 
                    : null,
                'body' => $response->json(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // temporary: to test resend api key
    Route::get('/test-email', function () {
        try {
            \Illuminate\Support\Facades\Mail::raw('Resend API key test successful in Bayanuk project!', function ($message) {
                $message->to('info@jxtechstudio.com') 
                        ->subject('Bayanuk Resend Test');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Email sent successfully via Resend!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });
   
    // Google Callback (GET request from Google)
    Route::post('/guest-token', [GuestTokenController::class, 'store']);

    // Authenticated Routes (Sanctum)
    Route::prefix('auth')->group(function () {
        Route::post('/magic-link', [AuthController::class, 'sendMagicLink']);
        Route::post('/magic-link/verify', [AuthController::class, 'verifyMagicLink']);
        Route::post('/google/redirect', [AuthController::class, 'googleRedirect']);
    });

    // Documents Routes
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store']);
        Route::get('/documents/{id}', [DocumentController::class, 'show']);
        Route::get('/documents/{id}/status', [DocumentController::class, 'status']);
        Route::get('/documents/{id}/file', [DocumentController::class, 'fileUrl']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

});
