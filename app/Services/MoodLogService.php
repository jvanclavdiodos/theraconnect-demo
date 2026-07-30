<?php

namespace App\Services;

use App\Exceptions\DuplicateMoodLogException;
use App\Models\MoodLog;
use App\Models\Patient;
use App\Support\MoodLogDates;
use Illuminate\Database\UniqueConstraintViolationException;

class MoodLogService
{
    public function today(): string
    {
        return MoodLogDates::today();
    }

    public function todayFor(Patient $patient, ?string $loggedOn = null): ?MoodLog
    {
        return $patient->moodLogs()
            ->whereDate('logged_on', $loggedOn ?? $this->today())
            ->first();
    }

    /**
     * @throws DuplicateMoodLogException
     */
    public function createDaily(Patient $patient, array $data): MoodLog
    {
        $loggedOn = $this->today();
        $existing = $patient->moodLogs()->whereDate('logged_on', $loggedOn)->first();

        if ($existing) {
            throw new DuplicateMoodLogException($existing);
        }

        try {
            return $patient->moodLogs()->create([
                ...$data,
                'logged_on' => $loggedOn,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $patient->moodLogs()
                ->whereDate('logged_on', $loggedOn)
                ->firstOrFail();

            throw new DuplicateMoodLogException($existing);
        }
    }
}
