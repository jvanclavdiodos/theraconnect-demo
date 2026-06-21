<?php

use App\Http\Controllers\Web\AuthenticatedSessionController;
use App\Http\Controllers\Web\ChatbotContentController;
use App\Http\Controllers\Web\ClinicianAvailabilityController;
use App\Http\Controllers\Web\ClinicianController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\NotificationLogController;
use App\Http\Controllers\Web\PatientController;
use App\Http\Controllers\Web\WebAppointmentController;
use App\Http\Controllers\Web\WebAssignmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth');
});

Route::middleware(['auth', 'role:admin,clinician'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Patients ─────────────────────────────────────────────────────────
    // index/show/create/store are admin + clinician (clinician queries are
    // scoped to their caseload; a clinician-created patient is auto-assigned to
    // them). Editing/deleting an existing record stays admin-only. `create`
    // is registered before the `{patient}` show route so it isn't swallowed.
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');

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
    });

    // Clinician availability calendar (JSON; powers the dashboard calendar).
    // Clinicians only; the controller always loads the current user's own
    // clinician, so there is no cross-clinician access.
    Route::middleware('role:clinician')->group(function () {
        Route::get('/availability/month', [ClinicianAvailabilityController::class, 'month'])->name('availability.month');
        Route::get('/availability/day', [ClinicianAvailabilityController::class, 'day'])->name('availability.day');
        Route::post('/availability/toggle-day', [ClinicianAvailabilityController::class, 'toggleDay'])->name('availability.toggleDay');
        Route::post('/availability/toggle-hour', [ClinicianAvailabilityController::class, 'toggleHour'])->name('availability.toggleHour');
    });

    // Appointments — list + status changes (Gate::authorize('manage') per row)
    Route::get('/appointments', [WebAppointmentController::class, 'index'])->name('appointments.index');
    Route::patch('/appointments/{appointment}/approve', [WebAppointmentController::class, 'approve'])->name('appointments.approve');
    Route::patch('/appointments/{appointment}/reject', [WebAppointmentController::class, 'reject'])->name('appointments.reject');
    Route::patch('/appointments/{appointment}/reschedule', [WebAppointmentController::class, 'reschedule'])->name('appointments.reschedule');

    // Assignments (Gate::authorize per assignment/submission)
    Route::get('/assignments', [WebAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/create', [WebAssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [WebAssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}/submissions', [WebAssignmentController::class, 'submissions'])->name('assignments.submissions');
    Route::get('/assignments/{assignment}/worksheet', [WebAssignmentController::class, 'downloadWorksheet'])->name('assignments.worksheet');
    Route::get('/submissions/{submission}/file', [WebAssignmentController::class, 'downloadSubmission'])->name('submissions.file');
    Route::patch('/submissions/{submission}/review', [WebAssignmentController::class, 'review'])->name('submissions.review');
});
