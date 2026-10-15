<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'List questions placeholder']);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Store question placeholder']);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['message' => 'Show question placeholder']);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Update question placeholder']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json(['message' => 'Delete question placeholder']);
    }
}
