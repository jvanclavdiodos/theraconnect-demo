<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_weekly_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')->constrained('clinicians')->onDelete('cascade');
            $table->enum('day_of_week', [
                'monday', 'tuesday', 'wednesday', 'thursday',
                'friday', 'saturday', 'sunday',
            ]);
            $table->boolean('is_available')->default(true);
            // Working-hours window for available days; null falls back to the
            // service default (08:00-16:00).
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            // One row per clinician per weekday.
            $table->unique(['clinician_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinician_weekly_availabilities');
    }
};
