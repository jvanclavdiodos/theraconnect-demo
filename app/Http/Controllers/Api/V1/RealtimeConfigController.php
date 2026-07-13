<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RealtimeConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $connection = config('broadcasting.connections.reverb');
        $enabled = config('broadcasting.default') === 'reverb'
            && filled($connection['key'] ?? null)
            && filled($connection['options']['host'] ?? null);

        return response()->json([
            'data' => [
                'enabled' => $enabled,
                'app_key' => $enabled ? $connection['key'] : null,
                'host' => $enabled ? $connection['options']['host'] : null,
                'port' => $enabled ? (int) $connection['options']['port'] : null,
                'scheme' => $enabled ? $connection['options']['scheme'] : null,
                'auth_endpoint' => url('/api/v1/broadcasting/auth'),
            ],
        ]);
    }
}
