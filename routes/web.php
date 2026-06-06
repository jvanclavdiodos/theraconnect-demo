<?php

use App\Http\Controllers\Web\AuthenticatedSessionController;
use App\Http\Controllers\Web\ChatbotContentController;
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

    // Patients CRUD
    Route::resource('patients', PatientController::class);

    // Appointments — list + status changes
    Route::get('/appointments', [WebAppointmentController::class, 'index'])->name('appointments.index');
    Route::patch('/appointments/{appointment}/approve', [WebAppointmentController::class, 'approve'])->name('appointments.approve');
    Route::patch('/appointments/{appointment}/reject', [WebAppointmentController::class, 'reject'])->name('appointments.reject');
    Route::patch('/appointments/{appointment}/reschedule', [WebAppointmentController::class, 'reschedule'])->name('appointments.reschedule');

    // Clinicians CRUD — admin only
    Route::middleware('role:admin')->group(function () {
        Route::resource('clinicians', ClinicianController::class)->except(['show']);
    });

    // Assignments
    Route::get('/assignments', [WebAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/create', [WebAssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [WebAssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}/submissions', [WebAssignmentController::class, 'submissions'])->name('assignments.submissions');
    Route::patch('/submissions/{submission}/review', [WebAssignmentController::class, 'review'])->name('submissions.review');

    // Chatbot content CRUD
    Route::resource('chatbot-content', ChatbotContentController::class)->except(['show']);

    // Notification audit log
    Route::get('/notifications/logs', [NotificationLogController::class, 'index'])->name('notifications.logs');
});
