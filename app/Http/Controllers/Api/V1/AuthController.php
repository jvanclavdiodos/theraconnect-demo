<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Clinician;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, PatientRequestService $patientRequests): JsonResponse
    {
        $user = DB::transaction(function () use ($request, $patientRequests) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'patient',
            ]);

            $patient = Patient::create([
                'user_id' => $user->id,
                'contact_no' => $request->contact_no,
                'gender' => $request->gender,
                'educational_attainment' => $request->educational_attainment,
                'employment_status' => $request->employment_status,
                'personal_issues' => $request->personal_issues,
            ]);

            if ($request->filled('requested_clinician_id')) {
                $patientRequests->submit($patient, Clinician::findOrFail($request->requested_clinician_id));
            }

            return $user;
        });

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        // The JSON API is the patient mobile-app surface — only patients are
        // permitted to mint bearer tokens here. Clinicians/admins must use the
        // session-authenticated web dashboard (`/login`). Mirrors
        // AuthenticatedSessionController::store which blocks patients from the
        // web login. Prevents personal_access_tokens pollution and account
        // enumeration via the API by non-patient roles.
        if ($user->role !== 'patient') {
            return response()->json([
                'message' => 'This account is not permitted to use the mobile app. Please use the web dashboard.',
            ], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(): Response
    {
        auth()->user()->tokens()->delete();

        return response()->noContent();
    }

    public function me(): JsonResponse
    {
        $user = auth()->user()->load('patient');

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'patient_profile' => $user->patient
                    ? new \App\Http\Resources\PatientResource($user->patient)
                    : null,
            ],
        ]);
    }
}
