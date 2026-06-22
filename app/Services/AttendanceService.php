<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Support\Collection;

/**
 * Attendance / engagement metrics, derived from appointment history — no extra
 * columns. A patient's engagement is one of the dimensions of therapy progress:
 * a no-show streak is the most common (and most clinically important) early
 * warning that someone is disengaging.
 */
class AttendanceService
{
    /** A patient is flagged at-risk at this many consecutive missed sessions. */
    public const AT_RISK_THRESHOLD = 2;

    /** Statuses that represent a session that has happened (or should have). */
    private const TERMINAL = ['completed', 'no_show', 'cancelled'];

    /**
     * Per-patient attendance summary:
     *  - attended / no_shows / cancelled / scheduled (totals)
     *  - attendance_rate: completed ÷ (completed + no_show), 0–100, null if none yet
     *  - consecutive_no_shows: length of the current trailing no-show streak
     *  - at_risk: streak ≥ AT_RISK_THRESHOLD
     */
    public function statsFor(Patient $patient): array
    {
        $appointments = $patient->appointments()
            ->whereIn('status', self::TERMINAL)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get(['id', 'status', 'scheduled_at']);

        $attended = $appointments->where('status', 'completed')->count();
        $noShows = $appointments->where('status', 'no_show')->count();
        $cancelled = $appointments->where('status', 'cancelled')->count();

        $kept = $attended + $noShows;
        $rate = $kept > 0 ? (int) round($attended / $kept * 100) : null;

        $streak = $this->trailingNoShowStreak($appointments);

        return [
            'attended' => $attended,
            'no_shows' => $noShows,
            'cancelled' => $cancelled,
            'scheduled' => $appointments->count(),
            'attendance_rate' => $rate,
            'consecutive_no_shows' => $streak,
            'at_risk' => $streak >= self::AT_RISK_THRESHOLD,
        ];
    }

    /**
     * Of the given patients, which are at-risk (consecutive no-show streak ≥
     * threshold). One query for the whole set — avoids an N+1 on the caseload
     * list. Returns a set of patient ids.
     *
     * @param  Collection<int, Patient>  $patients
     * @return array<int, bool>  patient_id => true
     */
    public function atRiskPatientIds(Collection $patients): array
    {
        $ids = $patients->pluck('id')->all();

        if (empty($ids)) {
            return [];
        }

        $byPatient = Appointment::whereIn('patient_id', $ids)
            ->whereIn('status', self::TERMINAL)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get(['patient_id', 'status', 'scheduled_at'])
            ->groupBy('patient_id');

        $atRisk = [];
        foreach ($byPatient as $patientId => $appointments) {
            if ($this->trailingNoShowStreak($appointments) >= self::AT_RISK_THRESHOLD) {
                $atRisk[(int) $patientId] = true;
            }
        }

        return $atRisk;
    }

    /**
     * Count consecutive no-shows at the tail of a chronologically-ordered
     * collection of terminal appointments. A 'completed' breaks the streak;
     * 'cancelled' (often clinic-side / mutual) is skipped, not counted.
     *
     * @param  Collection<int, Appointment>  $ordered  oldest → newest
     */
    private function trailingNoShowStreak(Collection $ordered): int
    {
        $streak = 0;

        foreach ($ordered->reverse() as $appointment) {
            if ($appointment->status === 'no_show') {
                $streak++;
            } elseif ($appointment->status === 'completed') {
                break;
            }
            // 'cancelled' is neutral — skip without breaking or counting.
        }

        return $streak;
    }
}
