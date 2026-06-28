<?php

namespace App\Services;

use App\Models\Clinician;
use App\Models\Notification;
use App\Models\Patient;

/**
 * Manages a patient's request to be assigned a clinician at sign-up and the
 * clinician's approval/denial of it. Approval is what actually places the
 * patient on the clinician's caseload (sets assigned_clinician_id), which is
 * the boundary the patients tab + messaging scope on.
 *
 * Each method writes the patient row AND emits a notification, so callers wrap
 * it in DB::transaction and dispatch the returned notification for push
 * (mirrors WebAppointmentController's approve/reject pattern).
 */
class PatientRequestService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Record a patient's request for a clinician (pending their approval) and
     * notify that clinician. Used by both the web and API registration flows.
     */
    public function submit(Patient $patient, Clinician $clinician): Notification
    {
        $patient->update([
            'requested_clinician_id' => $clinician->id,
            'clinician_request_status' => Patient::REQUEST_PENDING,
        ]);

        return $this->notifications->patientRequestSubmitted(
            $clinician->user->id,
            $patient->user->name,
        );
    }

    /**
     * Approve the request: place the patient on the requested clinician's
     * caseload and notify the patient. Returns the patient-facing notification.
     */
    public function approve(Patient $patient): Notification
    {
        $clinician = $patient->requestedClinician;

        $patient->update([
            'assigned_clinician_id' => $clinician->id,
            'clinician_request_status' => Patient::REQUEST_APPROVED,
        ]);

        return $this->notifications->patientRequestApproved(
            $patient->user->id,
            $clinician->user->name,
        );
    }

    /**
     * Decline the request (the patient keeps no clinician) and notify them so
     * they can choose another. Returns the patient-facing notification.
     */
    public function deny(Patient $patient): Notification
    {
        $patient->update([
            'clinician_request_status' => Patient::REQUEST_DENIED,
        ]);

        return $this->notifications->patientRequestDenied($patient->user->id);
    }
}
