<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::with('user')->orderByDesc('created_at');

        if ($request->filled('event')) {
            $query->where('event', 'like', '%' . $request->event . '%');
        }

        if ($request->filled('user')) {
            $term = $request->user;
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $logs = $query->paginate(50)->appends($request->query());

        return view('activity-logs.index', compact('logs'));
    }
}
