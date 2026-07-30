<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->general()
            ->latest()
            ->orderByDesc('id')
            ->paginate(Notification::PER_PAGE);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Notification $notification): JsonResponse
    {
        abort_unless(
            $notification->user_id === auth()->id()
                && $notification->type !== Notification::MESSAGE_RECEIVED,
            404
        );

        $notification->update(['read_at' => now()]);

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }
}
