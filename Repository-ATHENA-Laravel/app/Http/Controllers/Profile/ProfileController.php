<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['message' => 'Show profile placeholder']);
    }

    public function update(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Update profile placeholder']);
    }
}
