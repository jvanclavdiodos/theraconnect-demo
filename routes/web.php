<?php

use App\Http\Controllers\Portal\PortalAppointmentController;
use App\Http\Controllers\Portal\PortalAssessmentController;
use App\Http\Controllers\Portal\PortalAssignmentController;
use App\Http\Controllers\Portal\PortalChatbotController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalGoalController;
use App\Http\Controllers\Portal\PortalMessageController;
use App\Http\Controllers\Portal\PortalMoodLogController;
use App\Http\Controllers\Portal\PortalNoteController;
use App\Http\Controllers\Portal\PortalNotificationController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\AuthenticatedSessionController;
use App\Http\Controllers\Web\ChatbotContentController;
use App\Http\Controllers\Web\ClinicianAvailabilityController;
use App\Http\Controllers\Web\ClinicianController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\NotificationLogController;
use App\Http\Controllers\Web\PatientController;
use App\Http\Controllers\Web\PatientNoteController;
use App\Http\Controllers\Web\PatientRequestController;
use App\Http\Controllers\Web\ProgressController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\WebAppointmentController;
use App\Http\Controllers\Web\WebAssignmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::middleware(['guest', 'auth.no-store'])->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    // Combined login rate limiters (5/min/IP + 5/min per email|IP) — see
    // AppServiceProvider RateLimiter::for('login') / ('account-login'). They're
    // applied as separate middleware so they don't share a quota.
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware(['throttle:login', 'throttle:account-login']);

    // Patient self-registration (parity with the mobile register screen).
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
});

// Logout is shared by all authenticated roles (staff dashboard + patient portal).
Route::middleware(['auth', 'throttle:10,1'])->post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware(['auth', 'role:admin,clinician'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Staff member's own notifications inbox (bookings, messages, reschedules).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Account / profile picture (any staff user manages their own).
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:password-change')->name('account.password.update');
    Route::post('/account/avatar', [AccountController::class, 'updateAvatar'])->middleware('throttle:10,1')->name('account.avatar.update');
    Route::delete('/account/avatar', [AccountController::class, 'destroyAvatar'])->name('account.avatar.destroy');
    Route::get('/avatars/{user}', [AccountController::class, 'showAvatar'])->name('avatars.show');

    // ── Patients ─────────────────────────────────────────────────────────
    // index/show/create/store are admin + clinician (clinician queries are
    // scoped to their caseload; a clinician-created patient is auto-assigned to
    // them). Editing/deleting an existing record stays admin-only. `create`
    // is registered before the `{patient}` show route so it isn't swallowed.
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');

    // Approve/deny a self-registered patient's clinician request (admin, or the
    // requested clinician — enforced by PatientPolicy::respondToRequest).
    // Registered before the `{patient}` show route so they aren't swallowed.
    Route::post('/patients/{patient}/request/approve', [PatientRequestController::class, 'approve'])->name('patients.request.approve');
    Route::post('/patients/{patient}/request/deny', [PatientRequestController::class, 'deny'])->name('patients.request.deny');

    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');

    // Patient therapy-progress view (attendance + assessments + mood + goals).
    Route::get('/patients/{patient}/progress', [ProgressController::class, 'show'])->name('patients.progress');

    // ── Admin-only management ────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
        Route::match(['put', 'patch'], '/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');

        // Clinicians CRUD
        Route::resource('clinicians', ClinicianController::class)->except(['show']);

        // Chatbot knowledge base + notification audit log (clinic-admin tools)
        Route::resource('chatbot-content', ChatbotContentController::class)
            ->except(['show'])
            ->parameters(['chatbot-content' => 'intent']);
        Route::get('/notifications/logs', [NotificationLogController::class, 'index'])->name('notifications.logs');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    // Clinician availability calendar (JSON; powers the dashboard calendar).
    // Clinicians only; the controller always loads the current user's own
    // clinician, so there is no cross-clinician access.
    Route::middleware('role:clinician')->group(function () {
        Route::get('/availability/month', [ClinicianAvailabilityController::class, 'month'])->name('availability.month');
        Route::get('/availability/day', [ClinicianAvailabilityController::class, 'day'])->name('availability.day');
        Route::post('/availability/toggle-day', [ClinicianAvailabilityController::class, 'toggleDay'])->name('availability.toggleDay');
        Route::post('/availability/toggle-hour', [ClinicianAvailabilityController::class, 'toggleHour'])->name('availability.toggleHour');

        // Patient notes (clinician writes about a patient; manage own per Policy).
        Route::post('/patients/{patient}/notes', [PatientNoteController::class, 'store'])->name('patient-notes.store');
        Route::put('/patient-notes/{note}', [PatientNoteController::class, 'update'])->name('patient-notes.update');
        Route::delete('/patient-notes/{note}', [PatientNoteController::class, 'destroy'])->name('patient-notes.destroy');

        // Therapy progress: assign questionnaires + manage goals (caseload-gated).
        Route::post('/patients/{patient}/assessments', [ProgressController::class, 'assignAssessment'])->name('progress.assessments.assign');
        Route::post('/patients/{patient}/goals', [ProgressController::class, 'storeGoal'])->name('progress.goals.store');
        Route::post('/goals/{goal}/ratings', [ProgressController::class, 'rateGoal'])->name('progress.goals.rate');
        Route::patch('/goals/{goal}/status', [ProgressController::class, 'updateGoalStatus'])->name('progress.goals.status');

        // Messaging (patient <-> assigned clinician). Participant-only per Policy.
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages/open', [MessageController::class, 'open'])->name('messages.open');
        Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    });

    // Appointments — list + status changes (Gate::authorize('manage') per row)
    Route::get('/appointments', [WebAppointmentController::class, 'index'])->name('appointments.index');
    Route::patch('/appointments/{appointment}/approve', [WebAppointmentController::class, 'approve'])->name('appointments.approve');
    Route::patch('/appointments/{appointment}/reject', [WebAppointmentController::class, 'reject'])->name('appointments.reject');
    Route::get('/appointments/{appointment}/reschedule-slots', [WebAppointmentController::class, 'rescheduleSlots'])->name('appointments.reschedule-slots');
    Route::patch('/appointments/{appointment}/reschedule', [WebAppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::patch('/appointments/{appointment}/complete', [WebAppointmentController::class, 'complete'])->name('appointments.complete');

    // Assignments (Gate::authorize per assignment/submission)
    Route::get('/assignments', [WebAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/create', [WebAssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [WebAssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}/submissions', [WebAssignmentController::class, 'submissions'])->name('assignments.submissions');
    Route::get('/assignments/{assignment}/worksheet', [WebAssignmentController::class, 'downloadWorksheet'])->name('assignments.worksheet');
    Route::get('/submissions/{submission}/file', [WebAssignmentController::class, 'downloadSubmission'])->name('submissions.file');
    Route::get('/submissions/{submission}/preview', [WebAssignmentController::class, 'previewSubmission'])->name('submissions.preview');
    Route::patch('/submissions/{submission}/review', [WebAssignmentController::class, 'review'])->name('submissions.review');
});

/*
|--------------------------------------------------------------------------
| Patient browser portal (session auth, role:patient)
|--------------------------------------------------------------------------
| Full feature parity with the Flutter app. Thin controllers reuse the same
| Services + Policies as the API. Patients land here after login.
*/
Route::middleware(['auth', 'role:patient'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');

    // Appointments + clinician-first booking
    Route::get('/appointments', [PortalAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/book', [PortalAppointmentController::class, 'book'])->name('appointments.book');
    Route::post('/appointments', [PortalAppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{appointment}', [PortalAppointmentController::class, 'show'])->name('appointments.show');
    Route::delete('/appointments/{appointment}', [PortalAppointmentController::class, 'destroy'])->name('appointments.destroy');

    // Assignments + submissions
    Route::get('/assignments', [PortalAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/{assignment}', [PortalAssignmentController::class, 'show'])->name('assignments.show');
    Route::get('/assignments/{assignment}/worksheet', [PortalAssignmentController::class, 'downloadWorksheet'])->name('assignments.worksheet');
    Route::post('/assignments/{assignment}/submit', [PortalAssignmentController::class, 'submit'])->name('assignments.submit');
    Route::get('/submissions/{submission}/file', [PortalAssignmentController::class, 'downloadSubmission'])->name('submissions.file');

    // Messaging (single thread with the assigned clinician)
    Route::get('/messages', [PortalMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{conversation}', [PortalMessageController::class, 'send'])->name('messages.send');

    // Questionnaires (PHQ-9 / GAD-7)
    Route::get('/assessments', [PortalAssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/{assessment}', [PortalAssessmentController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessment}/submit', [PortalAssessmentController::class, 'submit'])->name('assessments.submit');

    // Mood check-ins
    Route::get('/mood', [PortalMoodLogController::class, 'index'])->name('mood.index');
    Route::post('/mood', [PortalMoodLogController::class, 'store'])->name('mood.store');

    // Goals + shared notes (read-only)
    Route::get('/goals', [PortalGoalController::class, 'index'])->name('goals.index');
    Route::get('/notes', [PortalNoteController::class, 'index'])->name('notes.index');

    // Notifications
    Route::get('/notifications', [PortalNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [PortalNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [PortalNotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Profile + avatar
    Route::get('/profile', [PortalProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [PortalProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['put', 'patch'], '/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [PortalProfileController::class, 'updatePassword'])->middleware('throttle:password-change')->name('profile.password.update');
    Route::post('/profile/avatar', [PortalProfileController::class, 'updateAvatar'])->middleware('throttle:10,1')->name('profile.avatar.update');
    Route::delete('/profile/avatar', [PortalProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::get('/profile/avatar', [PortalProfileController::class, 'avatar'])->name('profile.avatar');

    // Help assistant (chatbot)
    Route::get('/chatbot', [PortalChatbotController::class, 'index'])->name('chatbot.index');
    Route::post('/chatbot', [PortalChatbotController::class, 'message'])->middleware('throttle:30,1')->name('chatbot.message');
});
