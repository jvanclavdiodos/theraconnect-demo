<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_patient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['clinician_id', 'patient_id']);
            $table->index(['patient_id', 'clinician_id']);
        });

        DB::table('patients')
            ->whereNotNull('assigned_clinician_id')
            ->orderBy('id')
            ->chunkById(500, function ($patients) {
                $now = now();
                $rows = $patients->map(fn ($patient) => [
                    'patient_id' => $patient->id,
                    'clinician_id' => $patient->assigned_clinician_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('clinician_patient')->insertOrIgnore($rows);
            });

        // Recover every care relationship that can be proven from historical
        // appointment state, not only the one retained by the legacy column.
        DB::table('appointments')
            ->where(function ($query) {
                $query->whereIn('status', ['approved', 'rescheduled', 'completed', 'no_show'])
                    ->orWhere(function ($query) {
                        $query->where('status', 'cancelled')
                            ->whereNotNull('scheduled_at');
                    });
            })
            ->orderBy('id')
            ->chunkById(500, function ($appointments) {
                $now = now();
                $rows = $appointments->map(fn ($appointment) => [
                    'patient_id' => $appointment->patient_id,
                    'clinician_id' => $appointment->clinician_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('clinician_patient')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinician_patient');
    }
};
