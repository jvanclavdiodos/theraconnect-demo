<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatbotController;
use App\Http\Controllers\Api\V1\ClinicianController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\MoodLogController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PatientNoteController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SubmissionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mobile Patient Surface
|--------------------------------------------------------------------------
|
| [ARCHITECT DECISION] All mobile endpoints are prefixed /api/v1.
| Authenticated via Laravel Sanctum bearer tokens (§1.2).
| Rate limiters: auth=5/min/IP, chatbot=30/min/user, api=60/min/user.
|
*/

Route::prefix('v1')->group(function () {

    // Health check (public) — verifies the framework boots AND the database is
    // reachable. The Dockerfile HEALTHCHECK, Railway healthcheckPath, and
    // docker-compose healthcheck all probe this endpoint; without a DB probe a
    // deployment with a dead database would pass every health check.
    Route::get('/health', function () {
        try {
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            return response()->json(['status' => 'unhealthy', 'error' => 'db'], 503);
        }

        return response()->json(['status' => 'ok']);
    });

    // Auth (public, throttled). Two limiters applied as separate middleware so
    // they don't share a quota:
    //   throttle:login         — 5/min/IP (catches distributed attacking IPs)
    //   throttle:account-login — 5/min keyed by email|IP (catches brute-force
    //                            against one account from rotating IPs).
    // Register gets its own tighter bucket so a registration flood can't lock
    // out legitimate logins.
    Route::middleware(['throttle:login', 'throttle:account-login'])->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });
    Route::middleware(['throttle:register'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
    });
    Route::middleware(['throttle:register'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
    });

    // Clinician directory (public, throttled) — needed pre-auth so the register
    // screen can offer a preferred-clinician picker. Exposes only safe public
    // fields (id, name, specialization); also serves the authed booking flow.
    Route::middleware('throttle:60,1')
        ->get('/clinicians', [ClinicianController::class, 'index']);

    // Authenticated routes — patient-only per §1.6
    Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {

        // Profile
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:10,1');
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/auth/password', [PasswordController::class, 'update']);

        // Notifications
        Route::middleware('throttle:api')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

            // Device tokens
            Route::post('/device-token', [DeviceTokenController::class, 'store']);
            Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);

            // Profile
            Route::get('/profile', [ProfileController::class, 'show']);
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:10,1');
            Route::get('/profile/avatar', [ProfileController::class, 'avatar']);

            // (Clinician directory lives on the public route above — it is also
            // used by the pre-auth register screen.)

            // Appointments
            Route::get('/schedules', [AppointmentController::class, 'schedules']);
            Route::get('/schedules/availability', [AppointmentController::class, 'availability']);
            Route::get('/appointments', [AppointmentController::class, 'index']);
            Route::post('/appointments', [AppointmentController::class, 'store']);
            Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
            Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);

            // Assignments
            Route::get('/assignments', [AssignmentController::class, 'index']);
            Route::get('/assignments/{id}', [AssignmentController::class, 'show']);
            Route::get('/assignments/{id}/worksheet', [AssignmentController::class, 'downloadWorksheet']);
            Route::post('/assignments/{id}/submit', [SubmissionController::class, 'store']);
            Route::get('/submissions/{id}/file', [SubmissionController::class, 'downloadFile']);

            // Notes shared by the clinician (read-only)
            Route::get('/notes', [PatientNoteController::class, 'index']);

            // Therapy progress — standardized questionnaires (PHQ-9 / GAD-7)
            Route::get('/assessments', [AssessmentController::class, 'index']);
            Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
            Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit']);

            // Therapy progress — quick mood check-ins (1–10)
            Route::get('/mood-logs', [MoodLogController::class, 'index']);
            Route::post('/mood-logs', [MoodLogController::class, 'store']);

            // Therapy goals (read-only; clinician-authored, GAS-rated)
            Route::get('/goals', [GoalController::class, 'index']);

            // Messaging (patient <-> assigned clinician)
            Route::get('/conversations', [ConversationController::class, 'index']);
            Route::post('/conversations', [ConversationController::class, 'store']);
            Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
            Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send']);
        });

        // Chatbot (separate throttle: 30/min/user)
        Route::middleware('throttle:chatbot')->group(function () {
            Route::post('/chatbot/message', [ChatbotController::class, 'message']);
        });
    });
});
