<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Login endpoint placeholder']);
    }

    public function register(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Register endpoint placeholder']);
    }

    public function logout(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Logout endpoint placeholder']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Authenticated user profile placeholder']);
    }
}
