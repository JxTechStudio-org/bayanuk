<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class GuestTokenController extends Controller
{
    // POST /api/v1/guest-token: the backend generates the token and returns it once.
    // Only its SHA-256 hash is ever stored (documents.guest_token_hash, when the guest uploads).
    public function store(): JsonResponse
    {
        return response()->json([
            'token' => Str::random(64),
        ], 201);
    }
}
