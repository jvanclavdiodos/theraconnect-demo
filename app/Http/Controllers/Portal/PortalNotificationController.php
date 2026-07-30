<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->general()
            ->latest()
            ->orderByDesc('id')
            ->paginate(Notification::PER_PAGE);

        return view('portal.notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        abort_unless(
            $notification->user_id === $request->user()->id
                && $notification->type !== Notification::MESSAGE_RECEIVED,
            404
        );
        $notification->update(['read_at' => now()]);

        // AJAX clients (the notifications list) get a JSON envelope so they
        // can update the row styling in-place instead of full reload.
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'public_id' => $notification->public_id]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->unreadGeneral()
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
