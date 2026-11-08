<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $guestTokenHeader = $request->header('X-Guest-Token');

        // If a guest token header is passed, validate its format dynamically (64 alphanumeric chars)
        if ($guestTokenHeader && ! preg_match('/^[A-Za-z0-9]{64}$/', $guestTokenHeader)) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_guest_token',
                    'message' => __('messages.invalid_guest_token'),
                ],
            ], 400);
        }

        // Standard flow: Generate a new plain guest token
        $plainToken = Str::random(64);

        return response()->json([
            'token' => $plainToken,
        ], 201);
    }
}
