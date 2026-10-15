<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'List users placeholder']);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Store user placeholder']);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['message' => 'Show user placeholder']);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Update user placeholder']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json(['message' => 'Delete user placeholder']);
    }

    public function changeRole(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Change user role placeholder']);
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Toggle user status placeholder']);
    }
}
