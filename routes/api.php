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
use App\Http\Controllers\Api\V1\RealtimeConfigController;
use App\Http\Controllers\Api\V1\SubmissionController;
use App\Http\Controllers\Api\V1\UserGuideController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Mobile Patient Surface
|--------------------------------------------------------------------------
|
| All mobile endpoints are prefixed /api/v1. Authenticated patient endpoints
| use Laravel Sanctum bearer tokens. Rate limiters: auth=5/min/IP,
| chatbot=30/min/user, api=60/min/user.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        try {
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            return response()->json(['status' => 'unhealthy', 'error' => 'db'], 503);
        }

        return response()->json(['status' => 'ok']);
    });

    Route::middleware(['throttle:login', 'throttle:account-login'])->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });
    Route::middleware(['throttle:register'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
    });

    Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:10,1');
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/realtime/config', RealtimeConfigController::class);
        Route::post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
            ->middleware('throttle:60,1');
        Route::put('/auth/password', [PasswordController::class, 'update'])->middleware('throttle:password-change');

        Route::middleware('throttle:api')->group(function () {
            Route::get('/guide', UserGuideController::class);
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

            Route::post('/device-token', [DeviceTokenController::class, 'store']);
            Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);

            Route::get('/profile', [ProfileController::class, 'show']);
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:10,1');
            Route::get('/profile/avatar', [ProfileController::class, 'avatar']);

            // Clinician directory is only available after sign-in, for booking.
            Route::get('/clinicians', [ClinicianController::class, 'index']);

            Route::get('/schedules', [AppointmentController::class, 'schedules']);
            Route::get('/schedules/availability', [AppointmentController::class, 'availability']);
            Route::get('/appointments', [AppointmentController::class, 'index']);
            Route::post('/appointments', [AppointmentController::class, 'store']);
            Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
            Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);

            Route::get('/assignments', [AssignmentController::class, 'index']);
            Route::get('/assignments/{id}', [AssignmentController::class, 'show']);
            Route::get('/assignments/{id}/worksheet', [AssignmentController::class, 'downloadWorksheet']);
            Route::post('/assignments/{id}/submit', [SubmissionController::class, 'store']);
            Route::get('/submissions/{id}/file', [SubmissionController::class, 'downloadFile']);

            Route::get('/notes', [PatientNoteController::class, 'index']);

            Route::get('/assessments', [AssessmentController::class, 'index']);
            Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
            Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit']);

            Route::get('/mood-logs', [MoodLogController::class, 'index']);
            Route::post('/mood-logs', [MoodLogController::class, 'store']);

            Route::get('/goals', [GoalController::class, 'index']);

            Route::get('/conversations', [ConversationController::class, 'index']);
            Route::post('/conversations', [ConversationController::class, 'store']);
            Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
            Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send']);
        });

        Route::middleware('throttle:chatbot')->group(function () {
            Route::post('/chatbot/message', [ChatbotController::class, 'message']);
        });
    });
});
