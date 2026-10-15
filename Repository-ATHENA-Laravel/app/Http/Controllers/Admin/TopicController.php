<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TopicController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'List topics placeholder']);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Store topic placeholder']);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['message' => 'Show topic placeholder']);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Update topic placeholder']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json(['message' => 'Delete topic placeholder']);
    }
}
