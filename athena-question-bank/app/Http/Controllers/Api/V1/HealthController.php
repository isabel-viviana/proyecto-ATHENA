<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Comprobación básica de disponibilidad del microservicio ATHENA Question Bank.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'ATHENA Question Bank',
            'version' => '1.0.0',
            'status' => 'running',
        ]);
    }
}

