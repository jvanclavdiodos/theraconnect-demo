<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_date_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')->constrained('clinicians')->onDelete('cascade');
            $table->date('date');
            // Primary use is blocking a date (false). A windowed override with
            // is_available=true can add hours on a normally-off day.
            $table->boolean('is_available')->default(false);
            // Null start/end = the override applies to the whole day.
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['clinician_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinician_date_overrides');
    }
};
