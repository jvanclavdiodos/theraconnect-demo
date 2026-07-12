<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\PatientResource;
use App\Http\Resources\UserResource;
use App\Models\Patient;
use App\Models\User;
use App\Support\TermsOfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'patient',
                'terms_accepted_at' => now(),
                'terms_version' => TermsOfService::CURRENT_VERSION,
            ]);

            Patient::create([
                'user_id' => $user->id,
                'contact_no' => $request->contact_no,
                'gender' => $request->gender,
                'educational_attainment' => $request->educational_attainment,
                'employment_status' => $request->employment_status,
                'personal_issues' => $request->personal_issues,
            ]);

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
        // Lowercase the email so login is case-insensitive regardless of DB
        // collation. Pairs with the User::setEmailAttribute mutator that
        // lowercases on write.
        $email = strtolower($request->email);

        $user = User::where('email', $email)->first();

        // Anti-enumeration: always perform a Hash::check against the user's
        // real hash if found, or a freshly-minted dummy hash if the email does
        // not exist, so 401 timing is similar in both cases.
        $storedHash = $user?->password ?? Hash::make(str()->random(32));

        if (! $user || ! Hash::check($request->password, $storedHash)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        // The JSON API is the patient mobile-app surface; clinicians/admins
        // must use the session-authenticated web dashboard.
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
                    ? new PatientResource($user->patient)
                    : null,
            ],
        ]);
    }
}
