<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\DuplicateMoodLogException;
use App\Http\Controllers\Controller;
use App\Services\MoodLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalMoodLogController extends Controller
{
    public function __construct(private MoodLogService $moodLogs) {}

    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $logs = $patient->moodLogs()
            ->orderByDesc('logged_on')
            ->orderByDesc('created_at')
            ->take(60)
            ->get();
        $today = $this->moodLogs->today();
        $todayLog = $logs->first(fn ($log) => $log->logged_on->toDateString() === $today)
            ?? $this->moodLogs->todayFor($patient, $today);

        return view('portal.mood.index', compact('logs', 'todayLog'));
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:1,10'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->moodLogs->createDaily($patient, $validated);
        } catch (DuplicateMoodLogException $exception) {
            return redirect()
                ->route('portal.mood.index')
                ->with('status', $exception->getMessage());
        }

        return redirect()
            ->route('portal.mood.index')
            ->with('status', 'Mood check-in saved.');
    }
}
