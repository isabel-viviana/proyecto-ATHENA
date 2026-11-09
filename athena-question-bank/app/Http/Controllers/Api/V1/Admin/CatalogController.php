<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Listado oficial de áreas académicas ICFES.
     */
    public function subjects(): JsonResponse
    {
        $subjects = Subject::orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $subjects,
        ]);
    }

    /**
     * Listado de temas curriculares por materia.
     */
    public function topics(Request $request): JsonResponse
    {
        $query = Topic::query();

        if ($request->filled('subject_id')) {
            $query->where('subject_id', (int) $request->input('subject_id'));
        }

        $topics = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $topics,
        ]);
    }
}

