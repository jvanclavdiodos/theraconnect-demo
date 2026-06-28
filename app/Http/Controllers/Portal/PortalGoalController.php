<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalGoalController extends Controller
{
    /** Read-only: therapy goals are co-defined and GAS-rated by the clinician. */
    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $goals = $patient->therapyGoals()
            ->whereIn('status', ['active', 'met'])
            ->with('latestRating')
            ->orderByRaw("status = 'active' desc")
            ->latest()
            ->get();

        return view('portal.goals.index', compact('goals'));
    }
}
