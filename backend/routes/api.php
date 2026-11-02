<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GuestTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    // Guest Token Endpoint (Integration Contract Section 5)
    Route::post('/guest-token', [GuestTokenController::class, 'store']);

    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('/google/redirect', [AuthController::class, 'googleRedirect']);
    });

});
