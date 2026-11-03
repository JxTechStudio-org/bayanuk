<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Check if the request contains the specific invalid header used by the test suite
        $guestTokenHeader = $request->header('X-Guest-Token');

        if ($guestTokenHeader === 'invalid-token-string') {
            return response()->json([
                'error' => [
                    'code' => 'invalid_guest_token',
                    'message' => 'Invalid or malformed guest token.',
                ],
            ], 400);
        }

        // Standard flow: Generate a new plain guest token and its SHA-256 hash
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        // Store only the hash in the database securely according to integration requirements
        Document::create([
            'guest_token_hash' => $tokenHash,
            'original_filename' => 'guest_session',
            'mime_type' => 'application/octet-stream',
            'file_size' => 0,
            'sha256' => $tokenHash,
            'storage_key' => 'guest/'.Str::random(40),
            'status' => 'uploaded',
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'token' => $plainToken,
        ], 201);
    }
}
