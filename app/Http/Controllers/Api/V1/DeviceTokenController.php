<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceTokenResource;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        $deviceToken = DeviceToken::updateOrCreate(
            ['user_id' => auth()->id(), 'token' => $validated['token']],
            [
                'platform' => $validated['platform'],
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'data' => new DeviceTokenResource($deviceToken),
        ], 201);
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::where('user_id', auth()->id())
            ->where('token', $validated['token'])
            ->delete();

        return response()->noContent();
    }
}
