<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function index(): JsonResponse
    {
        $subjects = Subject::with('topics')->get();
        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Store subject placeholder']);
    }

    public function show($id): JsonResponse
    {
        $subject = Subject::with('topics')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $subject,
        ]);
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
