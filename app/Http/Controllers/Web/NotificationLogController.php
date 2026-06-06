<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\View\View;

class NotificationLogController extends Controller
{
    public function index(): View
    {
        $notifications = Notification::with('user')
            ->latest()
            ->paginate(30);

        return view('notifications.logs', compact('notifications'));
    }
}
