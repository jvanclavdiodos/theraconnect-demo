<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalMoodLogController extends Controller
{
    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $logs = $patient->moodLogs()->latest()->take(60)->get();

        return view('portal.mood.index', compact('logs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:1,10'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $patient->moodLogs()->create($validated);

        return redirect()
            ->route('portal.mood.index')
            ->with('status', 'Mood check-in saved.');
    }
}
