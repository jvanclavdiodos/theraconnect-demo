<?php

use Illuminate\Http\Request;
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

    // Health check (public)
    Route::get('/health', function () {
        return response()->json(['status' => 'ok', 'timestamp' => now()]);
    });

    // Auth (public, throttled)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
    });

    // Authenticated routes — patient-only per §1.6
    Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {

        // Profile
        Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        Route::get('/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);

        // Notifications
        Route::middleware('throttle:api')->group(function () {
            Route::get('/notifications', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
            Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead']);

            // Device tokens
            Route::post('/device-token', [\App\Http\Controllers\Api\V1\DeviceTokenController::class, 'store']);
            Route::delete('/device-token', [\App\Http\Controllers\Api\V1\DeviceTokenController::class, 'destroy']);

            // Profile
            Route::get('/profile', [\App\Http\Controllers\Api\V1\ProfileController::class, 'show']);
            Route::put('/profile', [\App\Http\Controllers\Api\V1\ProfileController::class, 'update']);
            Route::post('/profile/avatar', [\App\Http\Controllers\Api\V1\ProfileController::class, 'updateAvatar']);
            Route::get('/profile/avatar', [\App\Http\Controllers\Api\V1\ProfileController::class, 'avatar']);

            // Clinicians (read-only list for clinician-first booking)
            Route::get('/clinicians', [\App\Http\Controllers\Api\V1\ClinicianController::class, 'index']);

            // Appointments
            Route::get('/schedules', [\App\Http\Controllers\Api\V1\AppointmentController::class, 'schedules']);
            Route::get('/schedules/availability', [\App\Http\Controllers\Api\V1\AppointmentController::class, 'availability']);
            Route::get('/appointments', [\App\Http\Controllers\Api\V1\AppointmentController::class, 'index']);
            Route::post('/appointments', [\App\Http\Controllers\Api\V1\AppointmentController::class, 'store']);
            Route::get('/appointments/{id}', [\App\Http\Controllers\Api\V1\AppointmentController::class, 'show']);
            Route::delete('/appointments/{id}', [\App\Http\Controllers\Api\V1\AppointmentController::class, 'destroy']);

            // Assignments
            Route::get('/assignments', [\App\Http\Controllers\Api\V1\AssignmentController::class, 'index']);
            Route::get('/assignments/{id}', [\App\Http\Controllers\Api\V1\AssignmentController::class, 'show']);
            Route::get('/assignments/{id}/worksheet', [\App\Http\Controllers\Api\V1\AssignmentController::class, 'downloadWorksheet']);
            Route::post('/assignments/{id}/submit', [\App\Http\Controllers\Api\V1\SubmissionController::class, 'store']);
            Route::get('/submissions/{id}/file', [\App\Http\Controllers\Api\V1\SubmissionController::class, 'downloadFile']);

            // Notes shared by the clinician (read-only)
            Route::get('/notes', [\App\Http\Controllers\Api\V1\PatientNoteController::class, 'index']);

            // Therapy progress — standardized questionnaires (PHQ-9 / GAD-7)
            Route::get('/assessments', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'index']);
            Route::get('/assessments/{assessment}', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'show']);
            Route::post('/assessments/{assessment}/submit', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'submit']);

            // Therapy progress — quick mood check-ins (1–10)
            Route::get('/mood-logs', [\App\Http\Controllers\Api\V1\MoodLogController::class, 'index']);
            Route::post('/mood-logs', [\App\Http\Controllers\Api\V1\MoodLogController::class, 'store']);

            // Messaging (patient <-> assigned clinician)
            Route::get('/conversations', [\App\Http\Controllers\Api\V1\ConversationController::class, 'index']);
            Route::post('/conversations', [\App\Http\Controllers\Api\V1\ConversationController::class, 'store']);
            Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ConversationController::class, 'messages']);
            Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ConversationController::class, 'send']);
        });

        // Chatbot (separate throttle: 30/min/user)
        Route::middleware('throttle:chatbot')->group(function () {
            Route::post('/chatbot/message', [\App\Http\Controllers\Api\V1\ChatbotController::class, 'message']);
        });
    });
});
