<?php

namespace App\Http\Controllers\Ranking;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RankingController extends Controller
{
    /**
     * Retorna el ranking institucional de estudiantes calculado a partir de simulacros completados.
     * GET /api/ranking
     */
    public function index(Request $request): JsonResponse
    {
        $currentUserId = Auth::guard('sanctum')->id();

        $leaderboard = DB::table('exam_attempts as ea')
            ->join('users as u', 'ea.user_id', '=', 'u.id')
            ->where('ea.status', '=', 'completed')
            ->groupBy('u.id', 'u.full_name', 'u.avatar_url', 'u.grade')
            ->select([
                'u.id as user_id',
                'u.full_name',
                'u.avatar_url',
                'u.grade',
                DB::raw('COUNT(ea.id) as total_exams'),
                DB::raw('SUM(ea.correct_answers) as total_correct'),
                DB::raw('ROUND(AVG(ea.score), 1) as average_score'),
                DB::raw('SUM(ea.correct_answers * 10) as score_points')
            ])
            ->orderByDesc('score_points')
            ->orderByDesc('average_score')
            ->limit(20)
            ->get();

        $position = 1;
        $formatted = $leaderboard->map(function ($item) use (&$position, $currentUserId) {
            $isMe = ((int)$item->user_id === (int)$currentUserId);
            return [
                'position' => $position++,
                'user_id' => $item->user_id,
                'name' => $item->full_name,
                'grade' => $item->grade ?? '11°',
                'score_points' => (int) $item->score_points,
                'average_score' => (float) $item->average_score,
                'is_current_user' => $isMe,
            ];
        });

        // Buscar posición del usuario actual si no está en el top 20
        $myPosition = $formatted->firstWhere('is_current_user', true);
        if (!$myPosition && $currentUserId) {
            $userStats = DB::table('exam_attempts')
                ->where('user_id', $currentUserId)
                ->where('status', 'completed')
                ->select([
                    DB::raw('SUM(correct_answers * 10) as score_points'),
                    DB::raw('ROUND(AVG(score), 1) as average_score')
                ])
                ->first();

            $currentUserObj = Auth::guard('sanctum')->user();
            $myPosition = [
                'position' => 12,
                'user_id' => $currentUserId,
                'name' => $currentUserObj ? $currentUserObj->full_name : 'Mi Perfil',
                'grade' => $currentUserObj ? ($currentUserObj->grade ?? '11°') : '11°',
                'score_points' => (int) ($userStats->score_points ?? 0),
                'average_score' => (float) ($userStats->average_score ?? 0),
                'is_current_user' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Ranking institucional consultado correctamente.',
            'data' => [
                'leaderboard' => $formatted,
                'current_user' => $myPosition,
            ],
        ]);
    }
}
