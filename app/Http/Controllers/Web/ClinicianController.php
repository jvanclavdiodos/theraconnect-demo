<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClinicianController extends Controller
{
    public function index(): View
    {
        $clinicians = Clinician::with('user')->latest()->paginate(20);

        return view('clinicians.index', compact('clinicians'));
    }

    public function create(): View
    {
        return view('clinicians.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'clinician',
            ]);

            Clinician::create([
                'user_id' => $user->id,
                'license_no' => $validated['license_no'] ?? null,
                'specialization' => $validated['specialization'] ?? null,
                'contact_no' => $validated['contact_no'] ?? null,
            ]);
        });

        return redirect()->route('clinicians.index')
            ->with('status', 'Clinician created successfully.');
    }

    public function edit(Clinician $clinician): View
    {
        $clinician->load('user');

        return view('clinicians.edit', compact('clinician'));
    }

    public function update(Request $request, Clinician $clinician): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $clinician->user_id],
            'license_no' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:20'],
        ]);

        $clinician->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $clinician->update([
            'license_no' => $validated['license_no'] ?? $clinician->license_no,
            'specialization' => $validated['specialization'] ?? $clinician->specialization,
            'contact_no' => $validated['contact_no'] ?? $clinician->contact_no,
        ]);

        return redirect()->route('clinicians.index')
            ->with('status', 'Clinician updated successfully.');
    }

    public function destroy(Clinician $clinician): RedirectResponse
    {
        $clinician->delete();

        return redirect()->route('clinicians.index')
            ->with('status', 'Clinician deleted successfully.');
    }
}
