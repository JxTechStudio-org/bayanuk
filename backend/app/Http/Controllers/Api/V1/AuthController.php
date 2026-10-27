<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function sendMagicLink(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Magic link test OK']);
    }

    public function verifyMagicLink(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Verify test OK']);
    }

    public function googleRedirect(Request $request): JsonResponse
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function googleCallback(Request $request)
    {
        return response()->json(['message' => 'Google callback test OK']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Me test OK']);
    }

    public function logout(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Logout test OK']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        return response()->json(null, 204);
    }
}