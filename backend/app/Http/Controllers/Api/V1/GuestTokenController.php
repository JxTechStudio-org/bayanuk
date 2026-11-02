<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestTokenController extends Controller
{
    public function store(Request $request)
    {
        $plainToken = Str::random(64);

        $tokenHash = hash('sha256', $plainToken);

        return response()->json([
            'token' => $plainToken,
        ], 201);
    }
}