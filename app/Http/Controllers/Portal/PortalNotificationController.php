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
            ->latest()
            ->paginate(20);

        return view('portal.notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, int $id): JsonResponse|RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->update(['read_at' => now()]);

        // AJAX clients (the notifications list) get a JSON envelope so they
        // can update the row styling in-place instead of full reload.
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'id' => $id]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
