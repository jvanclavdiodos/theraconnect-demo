<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ClinicianDateOverride;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicianAvailabilityController extends Controller
{
    /** Weekday order for the form. */
    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday',
        'friday', 'saturday', 'sunday',
    ];

    /**
     * Show the logged-in clinician's weekly schedule + upcoming date blocks.
     * The clinician is always the current user — never a route-bound other
     * clinician — which is the ownership boundary for this page.
     */
    public function edit(Request $request): View
    {
        $clinician = $this->currentClinician($request);

        $existing = $clinician->weeklyAvailabilities->keyBy('day_of_week');

        $weekly = [];
        foreach (self::DAYS as $day) {
            $row = $existing->get($day);
            $weekly[$day] = [
                'is_available' => $row ? $row->is_available : true,
                'start_time' => $row && $row->start_time
                    ? substr($row->start_time, 0, 5)
                    : AvailabilityService::DEFAULT_START,
                'end_time' => $row && $row->end_time
                    ? substr($row->end_time, 0, 5)
                    : AvailabilityService::DEFAULT_END,
            ];
        }

        $overrides = $clinician->dateOverrides()
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get();

        return view('availability.index', compact('weekly', 'overrides'));
    }

    public function update(Request $request): RedirectResponse
    {
        $clinician = $this->currentClinician($request);

        $validated = $request->validate([
            'weekly' => ['required', 'array'],
            'weekly.*.start_time' => ['required', 'date_format:H:i'],
            'weekly.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        // For each day the patient can be offered, the end must be after the
        // start. (Unavailable days keep their times but they're ignored.)
        foreach (self::DAYS as $day) {
            $isAvailable = $request->boolean("weekly.$day.is_available");
            $start = $validated['weekly'][$day]['start_time'];
            $end = $validated['weekly'][$day]['end_time'];

            if ($isAvailable && $start >= $end) {
                return back()
                    ->withErrors(["weekly.$day.end_time" => ucfirst($day).": end time must be after start time."])
                    ->withInput();
            }

            $clinician->weeklyAvailabilities()->updateOrCreate(
                ['day_of_week' => $day],
                [
                    'is_available' => $isAvailable,
                    'start_time' => $start,
                    'end_time' => $end,
                ]
            );
        }

        return redirect()->route('availability.edit')
            ->with('status', 'Weekly availability saved.');
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $clinician = $this->currentClinician($request);

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // A date block makes the whole day unavailable (vacation / day off).
        // Keyed on (clinician, date) so re-blocking the same day is idempotent.
        $clinician->dateOverrides()->updateOrCreate(
            ['date' => $validated['date']],
            [
                'is_available' => false,
                'start_time' => null,
                'end_time' => null,
                'reason' => $validated['reason'] ?? null,
            ]
        );

        return redirect()->route('availability.edit')
            ->with('status', 'Date blocked.');
    }

    public function destroyOverride(Request $request, ClinicianDateOverride $override): RedirectResponse
    {
        $clinician = $this->currentClinician($request);

        // Ownership: a clinician may only remove their own blocks.
        abort_unless($override->clinician_id === $clinician->id, 403);

        $override->delete();

        return redirect()->route('availability.edit')
            ->with('status', 'Date block removed.');
    }

    private function currentClinician(Request $request)
    {
        $clinician = $request->user()->clinician;

        abort_unless($clinician !== null, 403, 'No clinician profile.');

        return $clinician;
    }
}
