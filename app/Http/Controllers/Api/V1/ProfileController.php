<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\PatientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $patient = auth()->user()->patient;

        if (! $patient) {
            return response()->json(['message' => 'Patient profile not found.'], 404);
        }

        return response()->json([
            'data' => new PatientResource($patient->load('user')),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $patient = auth()->user()->patient;

        if (! $patient) {
            return response()->json(['message' => 'Patient profile not found.'], 404);
        }

        $patient->update($request->validated());

        return response()->json([
            'data' => new PatientResource($patient->fresh()->load('user')),
        ]);
    }

    /** Upload/replace the patient's own profile picture. */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'local');
        $user->update(['avatar_path' => $path]);

        return response()->json([
            'data' => new PatientResource($user->patient->fresh()->load('user')),
        ]);
    }

    /** Serve the patient's own avatar inline (bytes fetched by the app). */
    public function avatar(): StreamedResponse
    {
        $user = auth()->user();

        abort_unless(
            $user->avatar_path && Storage::disk('local')->exists($user->avatar_path),
            404
        );

        return Storage::disk('local')->response($user->avatar_path);
    }
}
