<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'List notifications placeholder']);
    }

    public function markAsRead($id): JsonResponse
    {
        return response()->json(['message' => 'Mark notification as read placeholder']);
    }
}
