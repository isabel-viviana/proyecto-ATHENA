<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'List subjects placeholder']);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Store subject placeholder']);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['message' => 'Show subject placeholder']);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Update subject placeholder']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json(['message' => 'Delete subject placeholder']);
    }
}
